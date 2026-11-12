# Telephony live verification

This is the checklist you run against a real installation, with a real telephony
account and a real phone, before you trust it with customer traffic. Everything in
it has been proved against an automated test suite already. What it has not been
proved against is a live carrier, and that is what you are here to do.

It covers both supported providers, **Twilio first and then Plivo**. Run it in that
order even if you only care about Plivo: Twilio is the path this product has always
taken, so if something in the Twilio pass behaves oddly you know it is a regression,
and if the same thing only misbehaves on Plivo you know where it came from. Marking
the Twilio pass complete before you start the Plivo one is what makes that possible.

Every step tells you what to do, what to watch, what counts as a pass, and what to
write down. Write it down as you go — a call identifier you did not note at the time
is very hard to find again afterwards, and section 7 is the sheet you hand over.

---

## 1. Before you start

### 1.1 What this will cost you

**Real money, on your own account.** Every step below places a real call or sends a
real SMS, charged by your provider at your normal rates. Budget roughly:

- 10–14 voice calls, most under a minute, two of them 2–4 minutes;
- 6–10 SMS messages, one of them deliberately long enough to split into 3 parts;
- one AI-agent conversation per provider, which also spends your AI model credits
  (Deepgram and OpenRouter) on top of the call minutes.

For a US or European test number the whole pass is normally a few dollars per
provider. It is not zero, and if you run the AI-agent step several times it stops
being small — that step is charged per minute of conversation.

**A separate cost that is not on the phone bill:** if you buy a number for the Plivo
pass, that number keeps its monthly rental until you release it **in the Plivo
console**. Nothing in this product releases a number at the provider. Section 5 says
this again at the point where it matters.

### 1.2 What you need in front of you

- **One phone you own**, that can take calls and SMS, and whose number you are happy
  to have in the installation's contact list and KYC records. Use the same number
  throughout so the call history reads as one session.
- **A second calling device** for the call-queue steps only (T7, P7 and P9). The queue only
  engages when a caller arrives while you are already on a call, so those steps need
  two callers. A second mobile, a desk phone, a colleague's phone or a softphone app
  on a laptop all work. Note its number too.
- **A computer with a working microphone and a Chrome or Edge browser**, signed in to
  the installation as an administrator. The browser softphone needs microphone
  permission — grant it before you start, not mid-call.
- **Console access to the provider you are testing**: the Twilio Console for the
  Twilio pass, the Plivo Console for the Plivo pass. You will read call detail
  records and request logs there, and nothing in this product can show you those.
- **The installation's log**, at `storage/logs/laravel.log`. Keep a terminal open on
  it (see 1.6). Several checks are only visible there.
- **A purchased phone number in each provider account**, capable of voice and SMS in
  the country you are calling.

### 1.3 Choose how you will run the two passes

**Path A — two installations.** Install the release twice on the same server or two
servers, lock one to Twilio and one to Plivo, and run section 2 on the first and
section 3 on the second. This is the cleanest way: neither pass can disturb the
other, and you can go back to either one afterwards.

**Path B — one installation, reset in between.** Install once, lock it to Twilio, run
section 2 in full and sign it off. Then run **section 5** — the destructive reset —
and lock the same installation to Plivo, run section 3, and finally run section 5
again to return it to Twilio. Section 5 is itself one of the things you are
verifying, so on this path you run it twice and record both runs.

Path B is cheaper and it exercises the reset twice. Do not take it if the
installation holds anything you are not willing to lose: the reset clears telephony
credentials and trunks, retires phone numbers and wipes queue entries. Section 5
lists exactly what goes and what stays.

**Either way, the provider lock is permanent for an installation** except through
that reset. Choosing the provider at the end of the install wizard is not a setting
you can flip back later in Settings.

### 1.4 A public HTTPS address

Neither provider can reach an installation that is not on the public internet. Every
call and message in this checklist depends on the provider being able to POST back
to your server, and both providers require **HTTPS**, not plain HTTP.

- On a normal deployment, this is your real domain with a valid certificate. Nothing
  more to do.
- On a laptop or a staging box, put a tunnel in front of it (ngrok, Cloudflare Tunnel
  or the equivalent) and set `APP_URL` in `.env` to the tunnel's HTTPS address before
  you start. The application builds every callback URL from `APP_URL`; if it is wrong
  or still says `localhost`, the provider will call an address that does not exist
  and calls will connect and then go silent.
- After changing `APP_URL`, run `php artisan config:clear` and restart the queue
  workers, or they will keep building URLs from the old value.

**Confirm it before your first call.** From a machine that is not your server, open
`https://<your-address>/up`. You should get a page confirming the application is
healthy. If that does not load from the outside, nothing in this checklist will work
and you will spend money finding out.

### 1.5 Confirm the background processes are running

Campaigns, imports and scheduled work happen in queue workers. Without them a
campaign will sit at "running" and never dial, and you will conclude the provider is
broken when nothing has reached it.

If you use PM2 (the shipped configuration):

```bash
pm2 status
```

Expect `teleman-queue`, `teleman-queue-high`, `teleman-queue-campaigns` and
`teleman-scheduler` all **online**, with a low restart count. If any is stopped or
restarting in a loop, fix that first.

If you use Supervisor or systemd, check the equivalent units are active. If you are
testing on a laptop, two terminals are enough:

```bash
php artisan queue:work --queue=high,campaigns,default --tries=3
php artisan schedule:work
```

Leave both running for the whole session. **Restart the workers after any `.env`
change** — a worker holds its configuration in memory for its whole life.

One more quick sanity check, from the application itself:

```bash
php artisan install:selfcheck
```

This covers the platform basics — PHP version and extensions, writable storage, and
whether the application can rewrite its own `.env`. It says nothing about workers,
the scheduler or telephony, so a clean run is not a statement of readiness; a failing
one is still worth fixing before you spend money.

### 1.6 Where to look while you work

Keep these four open. Almost every check below points at one of them.

| Where | How to open it | What you get |
|---|---|---|
| The application log | `tail -f storage/logs/laravel.log` | The log lines each step names. This is the primary evidence. |
| The provider console | Twilio Console → Monitor → Logs → Calls / Messages; Plivo Console → Logs → Voice / Messages | The provider's own record of each call: its identifier, its status, its duration, its cost, and the requests it made to your server with their response codes. |
| The Calls page | Sign in → **Calls** | What the installation recorded: status, duration, recording. |
| The browser console and network tab | F12 in Chrome or Edge | Only needed for the softphone steps, when the browser is a participant rather than an observer. |

A useful filter while a step is running:

```bash
tail -f storage/logs/laravel.log | grep -E "TwiML|Inbound|AI Agent|Queue|Caller|Dequeue|SMS|Call status"
```

### 1.7 What to write down

For every step, record at minimum:

1. **The time** you did it, to the minute, in the server's timezone. Everything else
   is found from this.
2. **The provider's own call or message identifier** — Twilio calls it a SID
   (`CAxxxxxxxx`, `SMxxxxxxxx`), Plivo calls it a CallUUID or MessageUUID. Copy it
   from the provider console, not from memory.
3. **The installation's own call id**, from the Calls page.
4. **The log lines** the step names, copied verbatim, including their bracketed
   context.
5. **Pass or fail**, and if it failed, exactly what you saw instead.

Section 7 is a sheet with a row for each step. Fill it in as you go.

---

## 2. Twilio pass

Run this section on an installation locked to **Twilio**. Confirm the lock before you
begin: sign in as an administrator and open **Admin → Telephony**. The page names the
provider this installation uses. If it says Plivo, you are on the wrong installation.

**Credentials are entered elsewhere.** On a Twilio installation the Telephony page
shows the lock and a link onward; the account details themselves go in at
**Settings → Twilio**. Enter your Account SID and Auth Token there and save.

There is no "check these before saving" button on the Twilio side — saving is what
tries them. So confirm they took: after saving, the page should show the connected
account rather than an empty form or an error. If it does not, stop here; nothing
below can work without it.

### T1 — An outbound call, answered on a real phone

#### T1a From the browser softphone

**Do.** Signed in as an administrator, expand the softphone widget in the corner of
any page. Pick one of your Twilio numbers in the number switcher, type your test
phone's number into the dialpad, and press call. Grant microphone permission if the
browser asks.

**Watch.** Your test phone rings. Answer it and talk in both directions — say
something into the browser, then something into the phone.

**Pass.** The phone rings within about 10 seconds; audio is two-way and
intelligible; the softphone shows a running timer; hanging up on either end ends it
on the other. Afterwards, the **Calls** page shows the call with status `completed`
and a duration within a couple of seconds of what your phone shows.

**Record.** The Twilio Call SID from the Twilio console, the call id from the Calls
page, the time, and these log lines:

```
TwiML Request      {"call_id":…,"direction":…,"to":…,"from":"client:user_…"}
TwiML: Dial        …
Call status updated {"call_id":…,"status":"completed"}
```

The `from` value on a Twilio browser call always begins `client:`. Note it — the
Plivo pass asks the same question and the answer there is not known in advance.

#### T1b A one-contact campaign, with recording on

**Do.**

1. **Contacts → Add contact**: your test phone number. Save.
2. **Contact Lists → New list**: name it `Verification`, add that one contact.
3. **Campaigns → New campaign**:
   - Type: **Text to speech**
   - Script: one short sentence you will recognise, e.g. *"This is a verification
     call. Thank you."*
   - Caller number: your Twilio number
   - Contact list: `Verification`
   - Max concurrent calls: **1**
   - **Enable recording: on** — step T3 needs this
4. Note your credit balance, then **Launch**.

**Watch.** Your phone rings within about a minute. Answer it and listen to the whole
script.

**Pass.** You hear your sentence in full. The campaign's **Called** counter reaches
1 and the campaign moves to `completed` within a couple of minutes. Your credit
balance has dropped and a matching entry appears in your transaction history.

**Expected, and not a failure:** the campaign's **Answered** counter reads 0. That
tile counts only calls confirmed by answering-machine detection, which this
installation does not switch on. It reads 0 on Plivo always, and on Twilio unless
you have enabled detection yourself.

**Record.** The campaign id, the call id, the Twilio Call SID, the credit balance
before and after, and the transaction id.

**Do this step before step T6**, and in that order. T6 attaches an AI voice agent to
this same number, and from that moment any new campaign using the number becomes an
AI-agent campaign instead of a text-to-speech one. Running T1b afterwards would
quietly test something else.

### T2 — An inbound call reaching the browser softphone

**Do.**

1. **Phone Management → Number Inventory**. Find your number in the active-numbers
   list, tick its checkbox, and press **Configure Selected**. This points the
   number's voice and messaging webhooks at this installation. A number you have
   never configured will not reach the application at all. The list should then show
   it as configured.
2. Keep the softphone open and signed in, in a browser tab that is in the foreground.
3. From your test phone, call your Twilio number.

**Watch.** The softphone raises an incoming-call alert showing the caller's number.

**Pass.** The alert appears within a few seconds of the phone showing "calling";
answering it gives two-way audio; the call appears on the **Calls** page with
direction inbound.

**Record.** The Twilio Call SID, and this log line with its values:

```
Inbound Call Handler {"from":"+…","to":"+…","call_sid":"CA…"}
```

**If the alert never appears** but the phone connects and you hear a message, the
number's webhook is pointing somewhere else — repeat the Configure step and check
`APP_URL`.

### T3 — A recording is stored

**Do.** Nothing new — this uses the recorded campaign call from T1b. Recordings
arrive a short time after the call ends, so give it a minute or two.

**Watch.** Open the **Calls** page, find the T1b call, and open it.

**Pass.** A recording is attached and plays back, and what you hear is the call you
made. In the log:

```
Recording webhook processed {"call_id":…,"recording_sid":"RE…"}
```

**Record.** The recording SID and whether playback worked from the Calls page.

**If there is no recording**, check the Twilio console's request log for the call: a
non-200 response on the recording callback means the callback URL is unreachable
from Twilio, which is an `APP_URL` or certificate problem, not a recording problem.

### T4 — SMS out, including a long one

**Do.**

1. **SMS → Compose**. Sender: your Twilio number. Recipient: your test phone.
2. Send a short message you will recognise.
3. Note your credit balance, then send a **second** message of at least **400
   characters** — long enough that the carrier splits it into three parts. Note the
   balance again.

**Watch.** Both arrive on your phone. The long one arrives as one message on most
handsets; that is the phone reassembling the parts, and it is still three parts.

**Pass.** Both messages arrive. In the SMS conversation both show as outbound and
move from `sent` to `delivered`. **The long message costs about three times what the
short one did** — this product bills per segment, and a three-part message that
charged as one would be a real defect.

**Record.** Both message SIDs, the credit balance at all three points, and the
segment count Twilio reports for the long message in its console.

### T5 — SMS in

**Do.** From your test phone, reply to the message you just received.

**Watch.** The log, then the SMS conversation in the application.

**Pass.** Within a few seconds the log shows:

```
SMS Webhook Received {"provider":"twilio","payload":{…}}
```

and the reply appears in the conversation as inbound, attached to the same thread as
the outbound messages.

**Record.** The inbound message SID and the time between sending and its appearance.

**If nothing arrives**, the number's messaging webhook is not set — go back to
**Phone Management → Number Inventory** and run **Configure Selected** on it again.
(On Plivo there is no such step; see P5.)

### T6 — Two turns with an AI voice agent

This is the most expensive step. It spends call minutes, speech-to-text and AI model
credits. Keep it short and deliberate: two exchanges, then a third that is silence.

**Do.**

1. **AI Agents → New agent.** Give it a simple purpose and a greeting you will
   recognise, e.g. *"Hello, this is the verification agent. How can I help?"* Save it.
2. **Open that agent and press Edit.** Set its **Phone Number** to the number you
   have been testing with, and make sure **Active** is on. Save. The phone number
   field is on the edit screen, not the create one — this is the step that makes a
   campaign use the agent, and it is easy to miss.
3. **Campaigns → New campaign.** There is no "AI agent" campaign type: a campaign
   becomes an AI-agent campaign by using a caller number that has an active agent
   attached. So set the type to text to speech, choose **that same number** as the
   caller number, the `Verification` contact list, and max concurrent calls 1.
   Confirm in the log as you save:

   ```
   Auto-assigned AI agent to campaign {"campaign_name":…,"phone_number":"+…","ai_agent_id":…,"ai_agent_name":…}
   ```

   **If that line does not appear, stop and fix it before launching** — without it
   the campaign will simply read your text-to-speech script aloud and you will have
   verified nothing about the agent.
4. Launch, and answer your phone.
5. Wait for the greeting to finish.
6. **Turn one:** say a clear, ordinary sentence — *"I would like to know your opening
   hours."* Wait for the reply.
7. **Turn two:** say something that depends on turn one — *"And what about on
   Sundays?"* Wait for the reply.
8. **Turn three:** say **nothing at all** for at least fifteen seconds.
9. Hang up.

**Watch.** The log, live.

**Pass.**

- The greeting plays.
- Turn one gets a relevant spoken reply within a few seconds.
- Turn two gets a reply that clearly follows on from turn one — an agent that answers
  as though the call had just started is a failure of this step, not a quirk.
- The silence produces a spoken nudge: *"Are you still there? I didn't catch that —
  please go ahead."*
- After the call, the agent's call record shows **turn count 2** (the silence is not
  a turn), a duration, and a cost.

In the log, per turn:

```
AI Agent Call Handler     {"from":…,"to":…,"call_sid":…,"speech":"I would like to know your opening hours","direction":…}
AI Agent response generated {"agent_id":…,"call_id":…,"message":"…","pipeline_ms":…}
```

**Record.** The agent id, the call id, the provider call SID, both `speech` values as
the log shows them, both `pipeline_ms` figures, and the final turn count and duration
on the agent call record.

**Note the `pipeline_ms` figures.** They are the time from the caller finishing
speaking to the reply being ready. Anything much over 3000 will feel like a long
pause to the person on the phone, and is worth reporting even though it is a pass.

### T7 — The call queue: the caller waits, the agent joins

**This is the step that needs your second calling device.** A caller is only put in
the queue if you are already on a call.

**Do.**

1. Confirm the queue is on. **It is on by default** for every account, holding up to
   5 callers, each for up to 5 minutes. There is no screen for these settings — if
   the queue has been turned off on this account, turn it back on from the browser's
   developer console while signed in as the account that owns the number:

   ```js
   await window.axios.put('/call-queue/settings', { queue_enabled: true })
   ```

   (Do this as the customer or administrator who owns the number, not as an agent —
   agents are refused. On a fresh installation you can skip this entirely.)
2. **Caller A**: from your test phone, call your Twilio number. Answer it in the
   softphone. Stay on the line.
3. **Caller B**: from your second device, call the same number.
4. Listen on caller B.
5. Now hang up caller A.
6. In the softphone, the queue indicator shows callers waiting. Press **Connect
   next**.
7. Answer the call the softphone raises.

**Watch.** Caller B's handset, the softphone's queue indicator, and the log.

**Pass.**

- Caller B hears a greeting and then hold music, and stays connected.
- The queue indicator shows **1 caller(s) waiting**.
- After **Connect next**, the softphone rings; answering it puts you and caller B on
  the same call with two-way audio.
- The queue indicator then **disappears from the softphone entirely** — an empty
  queue shows nothing at all rather than saying so. Its absence is the observation.

In the log:

```
Inbound call enqueued {"user_id":…,"from":"+…","call_sid":"CA…","queue":"…"}
Caller dequeued       {"entry_id":…,"caller":"+…","attempt":1,"waited":…}
```

**Record.** Both call SIDs, the `entry_id`, the `waited` value, and whether the
caller heard their position read out or only a generic hold message.

### T8 — KYC phone verification

**Do.** Do this as an ordinary customer account, not as the administrator — the
verification flow belongs to customers.

1. Sign in as a customer (create one if you need to).
2. **Settings → KYC → Basic verification**. Enter your test phone number and request
   a code. Keep the SMS — you will need the real code at the end.
3. Enter a **wrong** 6-digit code. Then a second wrong one. Then a third.
   **On the third, a warning appears: "⚠️ Only 2 attempts remaining".** Nothing
   visible changes on the first two — see the note below.
4. Enter a fourth wrong code. The warning now reads **"Only 1 attempt remaining"**.
5. Enter a fifth wrong code. That spends the last of the five, and **the warning
   disappears** — it is only shown while at least one attempt is left.
6. Try a sixth time, with the **correct** code this time. It is **refused anyway**:
   the ceiling is spent, and no code works until a new one is issued. This is the
   limit doing its job.
7. Press **Resend code**. A new SMS arrives with a **different** code and a fresh set
   of five attempts.
8. Enter the **old** code from step 2 — refused. Then enter the **new** one — accepted,
   and the phone is verified.

**Pass.**

- The SMS arrives within seconds and contains a 6-digit code.
- The attempts warning appears at 2 remaining, then 1, then disappears at 0.
- With the ceiling spent, even the correct code is refused.
- Resend delivers a **different** code and restores the attempts.
- The superseded code is refused; the new one is accepted and the phone is verified.

**Judge every refusal by the attempts warning and by the phone staying unverified —
not by an error message.** This page does not display the refusal text at all, so a
wrong code produces no visible complaint: you will see the form simply not succeed.
That is a known shortfall in the page, not a fault in the verification. And because
the warning only appears once 2 or fewer of the 5 attempts remain, **the first two
wrong codes change nothing on screen** — which is why the step above uses three
before expecting to see anything.

**Record.** The message SIDs for each code sent, the exact text of the attempts
warning at 2 and at 1, what the sixth attempt did, and the time from request to SMS
arrival.

**Two things to watch for and report if you see them**, on either provider:

- **The resend button waits 60 seconds between presses, but only in the browser.**
  Nothing on the server limits how many codes can be requested, and every resend
  issues a fresh code with a fresh set of five attempts. Reloading the page clears
  the countdown. Note how many codes you requested in total, and the SMS cost.
- If any message on this page names a telephony provider, write it down verbatim.
  Nothing a customer sees should name the carrier behind a white-label product.

### T9 — Twilio pass sign-off

Before starting section 3, confirm every one of T1–T8 is marked pass in your sheet,
with identifiers recorded. If any is a fail, **stop here**: a failure on Twilio is a
regression in the product and there is no value in testing Plivo until it is
understood.

---

## 3. Plivo pass

Run this section on an installation locked to **Plivo** — the second installation on
Path A, or the same one after the reset on Path B.

**Read section 4 before you start this section.** Ten of the things the live pass
exists to settle can only be observed while these calls are happening, and several
of them need you to be looking at the Plivo console's request log for a specific
call. Knowing what to capture beforehand saves you re-running steps and re-spending
money.

**Confirm the lock and the credentials.** **Admin → Telephony** must name Plivo.
Enter your Plivo Auth ID and Auth Token and press the button that checks them
against Plivo before saving. Green before you continue.

**Get a number into the installation.** This works differently from Twilio, and it
is worth doing before anything else because every inbound step depends on it.

1. **Phone Management → Number Inventory**, and press **Sync Numbers**. Choose your
   test country and, if you want one, an area code. The inventory fills with numbers
   available to buy from Plivo for that country — this is Plivo's catalogue, not your
   account. The numbers you already own are listed separately, in the **Plivo Active
   Numbers** table higher up the same page.
2. Put one of them in a customer's hands. Either:
   - **the admin assigns it** — press **Assign Number**, pick the number and the
     account that should hold it; or
   - **a customer buys it** — signed in as a customer account whose basic
     verification is done, open **Phone Numbers → Purchase New**, pick the number and
     confirm. It is paid for with that account's credits and appears immediately
     under **Phone Numbers → My Numbers**.
3. Whichever route you took, confirm the number now shows against the account you
   will be testing with.

**There is no per-number webhook step on Plivo, and no Configure Selected button.**
A Plivo installation keeps one set of callback URLs for the whole installation and
attaches numbers to it automatically. If you come to this section from the Twilio
pass looking for T2's Configure step, that is why it is not here — nothing is
missing and there is nothing to press.

**A note on the customer route:** `Phone Numbers → Purchase New` needs the account's
basic verification finished first. If you plan to buy the number as a customer rather
than have the admin assign it, do **P8** before this — it is the step that completes
that verification. Assigning from the admin side has no such requirement, which is
why it is listed first.

Steps P1 to P8 are the same as T1 to T8. Do not skim them — the differences are in
what you observe, not in what you do.

### P1 — An outbound call, answered on a real phone

Do exactly what T1a and T1b describe.

**Additional pass conditions on Plivo:**

- **P1a is the evidence for check Q1.** Before you do anything else, capture the
  `TwiML Request` log line and copy its `from` value **exactly**, including any
  `sip:` prefix or `@` host. Then go on to Q1 in section 4.
- The `TwiML Request` line must be followed by `TwiML: Dial`. If instead you see
  `Inbound Call Handler`, stop — see Q1.
- **P1b is the evidence for check Q3.** While your phone is ringing and before you
  answer, watch the log for a `Call status updated` line. Note whether one appears
  during the ringing, or only after you hang up. Then go to Q3.

**Record**, in addition to T1's list: the Plivo **CallUUID** for each call (Plivo
Console → Logs → Voice), and the exact `from` string.

### P2 — An inbound call reaching the browser softphone

As T2, **but skip its first step**: there is no number to configure here. The number
you brought in above is already attached to the installation's callback URLs. Go
straight to keeping the softphone open in a foreground tab and calling your Plivo
number from your test phone.

**Record.** The CallUUID and the `Inbound Call Handler` line. Confirm the `from`
value is your test phone in `+`-prefixed international form — Plivo presents numbers
without the `+` and the installation adds it back, so a number here without a `+` is
worth reporting.

**If the call does not reach the softphone**, the thing to check is not a per-number
setting — it is that the number is assigned to the account whose softphone you have
open, and that `APP_URL` is the public address Plivo can reach.

### P3 — A recording is stored

As T3, using the recorded campaign call from P1b.

**Record.** The recording identifier and whether it plays from the Calls page.

### P4 — SMS out, including a long one

As T4, including the 400-character message.

**Pass**, additionally: the long message costs about three times the short one here
too. Plivo reports the real part count, and the charge must follow it.

**Record.** Both message UUIDs, all three credit balances, and the part count Plivo
reports in its console.

### P5 — SMS in

As T5. The log line reads `SMS Webhook Received {"provider":"plivo",…}`.

**Record.** The inbound message UUID and the delay.

**If nothing arrives**, ignore T5's advice about re-running Configure Selected —
there is no per-number messaging setting on Plivo. Check instead that the number is
assigned to the account you are testing with, that it has SMS capability (the
inventory shows this against the number), and that `APP_URL` is the public address
Plivo can reach.

### P6 — Two turns with an AI voice agent

As T6, with all three parts: turn one, turn two, then silence.

**This step is the evidence for check Q2**, which is about whether the caller's words
reach the application at all and what happens when there are none. Capture both
`AI Agent Call Handler` lines and their `speech` values before moving on, then go to
Q2 in section 4.

**One difference you should expect on Plivo, in campaigns that ask for a keypress**
(not this step): the script plays *inside* the question, so a caller can cut it short
by pressing a key early, and a caller who presses nothing hears a short English line
before the call ends. That is a documented difference between the providers, not a
fault.

### P7 — The call queue: the caller waits, the agent joins

As T7, with your second calling device, and **with more to observe**. This one step
is the evidence for six of the ten checks in section 4 — Q4, Q6, Q7, Q8, Q9 and Q10 —
so read them first and do this step slowly.

**Do**, extending T7:

1. Queue enabled, caller A answered in the softphone, caller B calling in and queued.
2. **Leave caller B on hold for at least three minutes** before going further. Listen
   the whole time. (Q10 depends on this wait.) Do not go much past four: a waiting
   caller is timed out at five minutes by default, which is correct behaviour but
   ends the call you are trying to observe.
3. Open the Plivo console's request log for **caller B's** call and leave it open.
   (Q6, Q7, Q10.)
4. Hang up caller A, press **Connect next**, and answer. (Q9.)
5. Talk on both ends, then **end the call from the softphone** and listen to caller
   B's handset for the next few seconds. (Q8.)
6. Then, for Q4, run one extra short call: with the softphone busy on another
   inbound call, call in from the second device again, wait until you hear the hold
   music, and **hang up while still on hold**, before any agent answers.

**Pass.** Everything T7 requires, plus: the hold music never stops during the
three-minute wait; the caller hears nothing unexpected when the agent ends the call;
and the abandoned caller leaves the queue indicator.

**Record.** Both CallUUIDs, the `entry_id` values, every `/plivo-xml/queue-*` request
in the console with its response code, and the full log sequence.

### P8 — KYC phone verification

As T8, the whole sequence: code received, five wrong codes with the attempts warning
appearing at 2 then 1 then vanishing, the ceiling refusing even the correct code,
resend restoring the attempts, the superseded code refused and the new one accepted.

**Record.** As T8. On Plivo the code is sent as an ordinary SMS from your own number,
so it also confirms P4's sending path from a second direction.

**If you are buying the test number as a customer rather than having the admin assign
it, this step has to come first** — see the note at the top of this section.

### P9 — The agent who does not answer

**Plivo only, and one of the more important steps here.** On Twilio a caller waits in
the provider's own queue and the next attempt takes them from the front of it, so an
agent who lets the phone ring costs the caller nothing. On Plivo the caller is in a
room that belongs to that one attempt, so the application has to give them back
deliberately. This step is what proves it does.

**Do.**

1. Get a caller into the queue exactly as in P7: caller A answered, caller B queued.
2. Hang up caller A.
3. Press **Connect next**.
4. **Do not answer.** Let the softphone ring until it stops on its own.
5. Watch the queue indicator and the log.
6. Press **Connect next** a second time, and this time answer.

**Pass.**

- After the unanswered attempt stops ringing, the queue indicator returns to
  **1 caller(s) waiting** — caller B is back in the queue and is still on the line
  hearing hold music.
- The log shows:

```
Agent leg ended unanswered; caller returned to the queue {"entry_id":…,"attempt":1,"user_id":…,"caller":"+…"}
```

- The second **Connect next** connects you to the same caller, and the log's
  `Caller dequeued` line shows **`"attempt":2`**.

**Repeat it once more, rejecting instead of ignoring**: press Connect next again from
a fresh queued caller and **decline** the incoming call in the softphone rather than
letting it ring out. The same log line must appear. If you can arrange a busy
condition, try that too.

**Record.** The agent leg's CallUUID for each attempt, the full log line each time,
the attempt numbers, and whether the caller's audio was ever interrupted.

**This step is the evidence for check Q5.** Capture the Plivo console's request log
entry for the agent's leg — specifically the POST to `/plivo-xml/queue-agent-hangup`
and every field in its body — before you move on.

**If the caller is not given back** — the indicator stays at 0 callers waiting, no
log line appears, and the second Connect next says there is nobody in the queue while
the caller is still holding — **stop and report it**. That caller is stranded and no
later attempt can reach them.

### P10 — Plivo pass sign-off

Confirm P1–P9 are all marked pass with identifiers recorded, and that section 4's ten
checks are each answered. Then go on to section 5.

---

## 4. Ten questions only a live call can answer

Everything else in this checklist confirms that something works. These ten confirm
*why* it works — each is a statement about how Plivo behaves that was taken from
documentation rather than from an observed live call, and that this pass is the first
opportunity to see for real.

Each one says where the evidence comes from, exactly where to look, what a pass looks
like, and what to do if it refutes the assumption. **Answer all ten and write the
answer down even when it passes** — a confirmed assumption is as valuable as a
refuted one, and the next person to touch this will not have your phone.

Where a check says **stop and report**, it means: do not continue the pass, do not
put the installation into service, and write down precisely what you saw including
the identifiers.

### Q1 — What a browser-originated call arrives as

**The question.** When the browser softphone places a call on Plivo, the caller is a
SIP endpoint rather than a phone number. The installation has to recognise it as one
of its own users' softphones. Plivo may present that endpoint as a full SIP URI
(`sip:username@…`) or as the bare username, and both are plausible. Only one is what
actually arrives.

**Evidence from.** Step P1a — the softphone call to your test phone.

**Where to look.** `storage/logs/laravel.log`, the `TwiML Request` line for that
call, its `from` field. Copy it exactly. Cross-check it against the **From** field on
the same call in the Plivo Console's call detail.

**Pass.** The call rings your test phone and connects, **and** the `TwiML Request`
line is followed by `TwiML: Dial`. That is what tells you the call was recognised as
coming from a browser.

**Refuted if.** The `TwiML Request` line is followed by `Inbound Call Handler`, or
the caller hears an inbound greeting, or the call is put into the call queue instead
of dialling out. That means the `from` value is a form the installation does not
recognise as one of its softphones, and every outbound softphone call on Plivo is
misrouted. **Stop and report**, quoting the `from` string exactly.

**Record either way.** The literal `from` string, and which of the two forms it is.

### Q2 — Whether spoken input arrives, and what happens when there is none

**The question.** Two halves. First, that the caller's transcribed words reach the
application under the field name it reads. Second — and this is the half that
matters more — that when the caller says **nothing**, Plivo does *not* post an empty
result, and instead follows the fallback the document carries. The entire
recovery from a silent caller rests on that fallback: without it a caller who simply
pauses is hung up on without a word.

**Evidence from.** Step P6 — turn one, turn two, and the deliberate silence.

**Where to look.**

- For the first half: the `AI Agent Call Handler` log line for turns one and two, its
  `speech` field. It must hold roughly what you actually said.
- For the second half: after fifteen seconds of silence, a **new**
  `AI Agent Call Handler` line must appear with `speech` empty or null, and you must
  hear the nudge on the phone. In the Plivo console's request log for that call, that
  moment shows a fresh request to `/plivo-xml/ai-agent-call`. It will most likely be
  a POST; a GET is equally fine and is **not** a refutation — the endpoint accepts
  both, and what matters is that the request happened at all.

**Pass.** `speech` carries your words on turns one and two; the silence produces a
new request and the spoken line *"Are you still there? I didn't catch that — please
go ahead."*

**Refuted if, first half.** `speech` is empty on a turn where you definitely spoke
clearly. Then the transcript is arriving under a name the application does not read,
and no AI agent on Plivo can hear anyone. **Stop and report**, with the full request
body Plivo shows for that call.

**Refuted if, second half.** After the silence the line simply goes dead: no nudge,
no new request in the Plivo console, no new log line. Then neither the empty result
nor the fallback arrived, and any caller who pauses gets hung up on. **Stop and
report.**

**A third outcome worth recording:** a new `AI Agent Call Handler` line appears with
`speech` empty **and** the console shows Plivo posted it (rather than the fallback
fetching it). That is a pass behaviourally and still worth noting, because it means
Plivo does post on empty input after all.

### Q3 — Whether a ringing call reports its status

**The question.** The installation asks Plivo to notify it both while a call is
ringing and when it ends, pointing both at the same address. Plivo's documentation
guarantees a call identifier on those notifications but does not promise the call's
**status** on the ringing one. If the status is missing, a call sits at whatever
status it was created with for its entire life and only moves when it ends — which
matters to anything that watches for calls that appear stuck.

**Evidence from.** Step P1b — the campaign call, watched while it rings.

**Where to look.** The log, live, between placing the call and answering it. You are
looking for a `Call status updated` line **during the ringing**:

```
Call status updated {"call_id":…,"status":"ringing"}
```

Then a second one after you hang up, with a final status.

**Read the `status` value, not the number of lines.** You will almost certainly see
two `Call status updated` lines either way — the ringing notification is processed
and logged whether or not it carried a usable status. Counting lines tells you
nothing. The answer is in what the **first** line's `status` says.

**Pass.** The first line — the one that appears while the phone is still ringing —
reads `"status":"ringing"`. The notification carried a real status and the call
advances mid-flight.

**Refuted if.** The first line appears at the right moment but its `status` is
anything else: the status the call already had (`initiated` or `queued`, repeated
back), or an unrecognised value. Then the ringing notification carries no status the
application can use, and a call's status does not advance until it ends — however
many log lines you counted.

**This one does not stop the pass.** Everything still works. Record the first line's
`status` value verbatim, and whether the request arrived at all (the Plivo console's
request log shows a POST to `/webhooks/plivo/calls/…/status` while the call was
ringing even when its body had no status in it).

### Q4 — Whether a caller who hangs up while waiting is recorded as abandoned

**The question.** When a waiting caller gives up and hangs up, Plivo should request
the room's action URL, and that request is the **only** way the installation learns
they are gone. If it never comes, the entry stays "waiting" for ever, counts against
the queue's size limit, and inflates every later caller's announced position.

**Evidence from.** Step P7 item 6 — the caller who hangs up while still on hold, with
no agent having answered.

**Where to look.** The log, within a few seconds of the hang-up:

```
Queue result received {"provider":"plivo","call_sid":"…"}
Caller abandoned queue {"entry_id":…,"caller":"+…","waited_seconds":…}
```

And in the Plivo console's request log for that caller's call, a POST to
`/plivo-xml/queue-result` with a 200 response.

**Pass.** Both log lines appear, and the softphone's queue indicator **disappears** —
an empty queue renders nothing, so its absence is what you are looking for, not a
"nobody waiting" message.

**Refuted if.** Neither line appears and the indicator still shows a caller waiting
after the caller has hung up. Then abandoned callers accumulate. **Stop and report** —
this makes the queue progressively less usable rather than failing outright, which is
worse, because nobody notices for weeks.

**Record either way.** Both log lines or their absence, the response code on the
`/plivo-xml/queue-result` request, and what the indicator did.

### Q5 — What the end of an unanswered agent leg reports

**The question.** Two things at once. First, that Plivo notifies the hangup address
for a leg that was **never answered** — rang out, was rejected, or hit a busy signal.
The whole give-the-caller-back recovery rests on that notification arriving for a
call nobody ever picked up. Second, what that notification actually contains beyond
the call identifier, which the documentation does not settle.

**Evidence from.** Step P9, all its variants: ring-out, reject, and busy if you can
produce one.

**Where to look.**

- **The log**, for each variant:

```
Agent leg ended unanswered; caller returned to the queue {"entry_id":…,"attempt":…,"user_id":…,"caller":"+…"}
```

- **The Plivo console's request log for the agent's own leg** (not the caller's):
  find the POST to `/plivo-xml/queue-agent-hangup?entry_id=…&attempt=…` and **copy
  every field name and value in its body**. Note in particular whether it carries a
  call status, a hangup cause, a duration, or a direction — and whether anything in
  it distinguishes a leg that rang out from one that was answered and then ended.

**Pass.** The log line appears within a few seconds of the leg stopping, for **every**
variant you tested, and the caller comes back to the queue.

**Refuted if.** For any variant, the notification never arrives. That caller is
stranded in a state no later attempt can reach, still on the line, hearing music
nobody will interrupt. **Stop and report**, naming which variant failed.

**Record either way.** The body's full field list, verbatim. This is the single most
valuable payload to capture in this whole checklist — it is currently unknown, the
handler deliberately reads nothing from it, and the next person to work on this
needs to know what is in it.

### Q6 — Whether the hold document knows which caller it is for

**The question.** When a caller is waiting, Plivo fetches a document to play them,
and the installation reads their place in the line out to them. It can only do that
if the fetch identifies the call. If it does not, the caller gets a generic message
instead — which is not broken, but is silently less than intended for everyone.

**Evidence from.** Step P7, while caller B is on hold.

**Where to look.** **Caller B's handset, first.** They hear one of two things:

- *"You are number 1 in the queue. Please hold."* — the fetch identified the call.
- *"Please hold. An agent will be with you shortly."* — it did not.

Then confirm in the Plivo console's request log for caller B's call: the POST to
`/plivo-xml/queue-wait`, and whether its body contains a call identifier.

**A stronger version, if you have a third calling device:** queue two callers. The
second should hear *"You are number 2"*. If both hear "number 1", the position is not
being read from the right place even though an identifier arrived.

**Pass.** The numbered sentence is spoken, and it is the right number.

**Refuted if.** The generic sentence is spoken. **Do not stop the pass** — the caller
still waits correctly and is still connected. Record it and report it: it means no
caller on Plivo ever hears their position, which is a customer-visible shortfall
nobody would otherwise notice.

**Record either way.** The exact sentence the caller heard, and whether the request
body carried a call identifier.

### Q7 — Whether the hold document's fetch is signed like every other request

**The question.** Every request from Plivo to this installation is checked for a
valid signature, and an unsigned one is rejected. If the hold-document fetch does not
carry that signature the way the others do, it will be refused — and the visible
symptom is not an error page anywhere, it is a caller sitting in **complete silence**
while the queue works perfectly otherwise.

**Evidence from.** Step P7, the same hold as Q6.

**Where to look.** The Plivo console's request log for caller B's call, the entry for
`/plivo-xml/queue-wait`: **its response code**.

**Pass.** The response is **200**, and caller B hears the greeting and hold music.

**Refuted if.** The response is **403**, and caller B hears nothing at all after the
greeting. Then the fetch is not signed the way the rest are. **Stop and report** —
capture the request headers Plivo shows for that entry if the console exposes them,
and note that the caller's line was silent rather than disconnected.

**Record either way.** The response code, and whether the caller heard music.

**A note on telling this apart from other silence:** a 200 with silence is a
different problem (see Q10). Check the code before concluding anything.

### Q8 — Whether a completed queued call ends cleanly

**The question.** The document a waiting caller is given ends with a spoken line for
the case where the room cannot be joined at all — *"We are sorry, the queue is no
longer available. Goodbye."* That line must never be reached after a call that went
perfectly well. Whether it is reached depends on the response to the room's action
request taking over the call rather than the original document simply continuing.

**Evidence from.** Step P7 item 5 — the agent ends a successfully connected queued
call.

**Where to look.** **Caller B's handset**, in the two or three seconds after the
agent hangs up. Listen; do not hang up yourself first, or you will not hear it.

**Pass.** The call simply ends. Nothing is spoken.

**Refuted if.** After a perfectly good conversation, the caller hears *"We are sorry,
the queue is no longer available. Goodbye."* **Do not stop the pass** — the call
itself worked. Record it and report it: it is a confusing, customer-facing wrong
message at the end of every successful queued call on Plivo.

**Record either way.** Exactly what the caller heard after the agent hung up, and
whether the same thing happens when the **caller** hangs up first instead (try it
once — it is a different path and worth one extra call).

### Q9 — Whether the waiting caller's number can be shown to the agent

**The question.** When the agent's softphone is rung, the caller's own number is
presented as the calling number, so the agent sees who is waiting. Plivo may refuse
that number as the source of a call to a SIP endpoint. If it does, the call is never
placed and **every dequeue fails** — the queue does not work at all.

**Evidence from.** Step P7 item 4 — pressing **Connect next**.

**Where to look.** The log, immediately after you press the button:

```
Dequeue call initiated {"entry_id":…,"user_id":…,"caller":"+…"}
```

or, if Plivo refused:

```
Failed to initiate dequeue call {"entry_id":…,"error":"…"}
```

**Pass.** `Dequeue call initiated`, the softphone rings, and the incoming-call alert
shows **the waiting caller's number**.

**Refuted if.** `Failed to initiate dequeue call` appears with an error mentioning
the source number, the caller ID, or the `src` field — and the caller stays on hold
while nothing rings. **Stop and report**, quoting the error text exactly. Nothing
about the queue works on Plivo until this is resolved.

**A partial outcome to record:** the call is placed but the agent's alert shows
something other than the caller's number (the installation's own number, or a blank).
That is a pass for the queue and a shortfall for the agent; note what was displayed.

### Q10 — How often the hold document is fetched, and whether the music lasts

**The question.** The hold document is expected to be fetched **once** for a waiting
caller, not repeatedly, which is why the music inside it is set to play on a loop. If
that expectation is wrong in either direction — fetched once but the loop ignored, or
the music simply stopping — the caller is left on a silent line for the rest of
their wait, with everything else working.

**Evidence from.** Step P7 item 2 — the deliberate three-minute hold.

**Where to look.** Two places.

- **Caller B's handset**, for the full three minutes. Does the music play
  continuously?
- **The Plivo console's request log for caller B's call.** Count the requests to
  `/plivo-xml/queue-wait`.

**Pass.** The music plays for the whole three minutes without a gap, whatever the
count is. Record the count.

**Not a failure:** a caller held past **five minutes** is timed out on purpose, and
the log says so (`Queue entry timed out`). Keep the hold under four minutes so you
are observing the music and not the timeout.

**Refuted if.** The music stops partway — typically after one pass of the track,
around a minute in — and the caller hears silence for the rest of the wait while the
console shows only one request. **Stop and report**, with the count and the time at
which the music stopped.

**Record either way.** The number of `/plivo-xml/queue-wait` requests, the length of
the hold, and whether audio was continuous throughout.

---

## 5. The destructive reset, end to end, then back to Twilio

The provider an installation uses is fixed at install time. The one way to change it
afterwards is this reset, and it is destructive by design. Verify it deliberately,
with your eyes open, because a customer who runs it by accident has no undo.

Run this section:

- on **Path B**, twice: once between the Twilio pass and the Plivo pass, and once at
  the end to return to Twilio;
- on **Path A**, once at the end on either installation, to verify the reset itself.

**Take the steps in the order they are written, and do not skip ahead to the reset
itself.** R3 checks how the console command refuses, and two of those refusals can
only be seen while the installation still has a provider locked. R4 is what removes
the lock. Run R3 first and both checks work; run the reset first and every console
command from then on answers with the same "nothing to reset" message, which looks
like a broken command and is not one.

### R1 — Know what you are about to lose

The reset **deletes**, for every provider configured on the installation — not only
the one it is locked to:

- telephony account credentials;
- SIP and BYOC trunks;
- softphone endpoints;
- call queue entries.

It **retires but does not delete** your phone numbers: the rows stay, because your
call history points at them, but they are unassigned and retired in this
installation. Campaigns and AI agents lose the number they called from, and you will
have to set that again afterwards.

It **cancels** any number requests still awaiting a decision.

It **keeps**: users, credits, contacts, campaigns, call history and SMS history.

**And the thing that catches people out:** nothing is released at the provider.
Your numbers stay live in your Twilio or Plivo account and keep billing you monthly.
So do trunks and applications. If you want them gone, cancel them in the provider's
own console yourself — this reset never touches it.

### R2 — Record the state you are resetting from

Before you run it, write down:

- the provider currently locked;
- every phone number in **Phone Management → Number Inventory**, with the account it
  is assigned to;
- your credit balance;
- the number of contacts, campaigns, calls and SMS messages, from the dashboard.

You will check all of these again in R6.

### R3 — The console reset, and how it refuses

**Do this while the installation is still locked, before the reset in R4.** Nothing
here changes anything — every step is a refusal or a declined prompt — but neither
can be seen once the lock is gone, and R4 is what takes it away.

There is a console route to the same reset, for support work on an installation
whose administrator cannot reach the admin panel. One thing about it is worth knowing
before you run it, and it is the whole reason this step exists.

**The typed confirmation is an option, not a prompt.** The command asks *whether* to
continue, but it never asks you to type the provider name — you supply that up front,
and without it the run is refused after you have already answered yes:

```bash
php artisan telephony:reset --provider=twilio
```

Use `--provider=plivo` on a Plivo installation. (`--force` skips the yes/no question
as well; do not use it here — the question is part of what you are checking.)

**Do**, in this order, on the still-locked installation:

1. **Run it bare:** `php artisan telephony:reset`. It prints the same warnings the
   admin page shows and asks **Continue?**. Answer **yes**. It refuses:
   *"The confirmation did not match. Type "twilio" exactly to reset this
   installation's telephony provider."* — and changes nothing. That is the missing
   option doing its job.
2. **Run it bare again and answer no.** It prints *"Aborted. Nothing was changed."*
3. Read the warning text it printed in step 1 and check it says what R1 says.

**Pass.** Both refusals behave as described, the warning text matches R1, and nothing
in the installation changed — it is still locked to the same provider, and you can
confirm that on **Admin → Telephony** before moving on.

**Record.** Both messages, and the warning text.

**Do not run it to completion here.** R4 is where the reset actually happens, and
doing it from the admin panel is what exercises the password gate and the audit
record. The one time to run this command through to the end is Path B's second reset,
and then only with `--provider=<name>`; if you do, record the tables and row counts it
prints, and the list of retired numbers it warns are **still live and still billing**
at the provider.

### R4 — Run the reset from the admin panel

**Do.** **Admin → Telephony**, find the danger zone, and start the reset. You will be
asked to:

1. **Confirm your password.** This is the same gate that protects the system
   configuration page — destroying a telephony setup asks for no less than reading
   one.
2. **Type the name of the provider currently locked**, to confirm.

Read the warning text on the way past and check it says what R1 says — this is the
warning a customer gets, and it is the only one they get.

**Pass.** The reset completes and the installation is no longer locked to a provider.
You are taken to the provider chooser.

**Record.** The exact confirmation you had to type, the counts the result reports,
and the audit reference if one is shown.

#### If the reset refuses

It is designed to refuse rather than tear down telephony underneath live traffic, and
after a pass full of test calls a refusal is likely. It is the interlock working.
**Do not record it as a failure.** You will see one of these:

- *"Cannot reset telephony while N campaign(s) are running or scheduled. Pause or
  complete them first, then try again."* — Open **Campaigns** and pause or complete
  every campaign that is running, and any you scheduled earlier and forgot. The
  verification campaigns from T1b and T6 are the usual culprits.
- *"Cannot reset telephony while N call(s) are still in progress. Wait for them to
  finish, then try again."* — A call is still connecting or connected. Usually you
  just need to wait a minute. If the count will not go down, you have a call whose
  final status never arrived and which is therefore stuck short of finishing —
  **which is exactly what check Q3 is about.** Note it against Q3, then clear it
  before retrying.
- *"The confirmation did not match. Type "twilio" exactly to reset this
  installation's telephony provider."* — You typed the wrong word. It wants the
  provider's name in lower case, though case and stray spaces are forgiven.

Nothing is changed by a refusal. Fix what it names and run it again.

**Record.** Which refusal you hit, if any, and what you had to clear to get past it.
A refusal that names a stuck call is a finding, not an inconvenience.

#### One last check, now that nothing is locked

With the reset done and no provider locked, run the console command once more:

```bash
php artisan telephony:reset
```

**Expected — this is not a failure.** It refuses immediately, before any warning or
any question: *"This installation is not locked to a telephony provider, so there is
nothing to reset."* That refusal comes first, ahead of everything else the command
does, which is why R3's two checks had to happen while the installation was still
locked.

**Record.** The message.

### R5 — Lock the next provider

**Do.** On the chooser you landed on, select the provider you want next — Plivo on
Path B's first reset, Twilio on the final one — and confirm.

**Pass.** **Admin → Telephony** now names the provider you chose.

The reset cleared every stored credential, and where you confirm that depends on
which provider you just locked:

- **Plivo** — the Telephony page's own Auth ID and Auth Token fields are empty.
- **Twilio** — the Telephony page has no credential fields to be empty. Check
  **Settings → Twilio** instead: it should show no connected account.

**Note:** this chooser is the admin route, not the install wizard. If at any point
you find yourself back in the installation wizard on an installed application, stop
and report it.

### R6 — Confirm what survived

Check every item from R2:

- **Gone, as intended:** credentials are blank; no trunks; no softphone endpoints;
  the queue is empty.
- **Retired but present:** every phone number still appears in **Phone Management →
  Number Inventory**, unassigned and retired. None has disappeared.
- **Untouched:** credit balance identical; contacts, campaigns, call history and SMS
  history all at the counts you recorded.

**Record.** Any difference from R2 that R1 does not account for. A missing contact, a
changed balance or a vanished call record is a failure, not a side effect.

### R7 — Re-lock to Twilio and confirm the installation still works

This is the final state. An installation left un-locked or half-configured is not a
verified one.

**Do.**

1. Lock the installation to **Twilio** (R5, choosing Twilio).
2. **Settings → Twilio**: enter your Account SID and Auth Token and save. Confirm
   the page comes back showing the connected account — saving is what tries them,
   and nothing below works until it sticks.
3. **Phone Management → Number Inventory**: your numbers are retired. Run **Sync
   Numbers** to pick them up again, assign the one you tested with back to your
   account, then tick it and press **Configure Selected**.
4. Re-attach that number to the campaign and the AI agent you used in section 2, both
   of which lost theirs in the reset.
5. **Re-run T1a and T4** — one softphone call and one SMS. Nothing more.

**Pass.** The call connects with two-way audio and the SMS arrives, on the same
installation, after a full reset and re-lock. That is what proves the reset is
recoverable rather than merely destructive.

**Record.** The new call SID and message SID, and anything you had to reconfigure
that R1 did not warn you about — that list is worth reporting on its own.

### R8 — Tidy up at the provider

Finally, and outside this product: in your **Plivo** console, release any number you
bought only for this verification, and remove any application or trunk the pass
created. Do the same in **Twilio** if you bought a number for it. Nothing in this
checklist did that for you, and those numbers bill monthly until you do.

**Record.** What you released, and when.

---

## 6. If a step fails

Before reporting a failure, rule out the four things that account for most of them:

1. **The workers are not running.** `pm2 status`, or your two terminals. A campaign
   that never dials is almost always this.
2. **`APP_URL` is wrong.** Every callback address is built from it. Symptom: the call
   connects and then goes silent, or nothing ever appears in the log. Check
   `https://<your-address>/up` from outside the server, then
   `php artisan config:clear` and restart the workers.
3. **The number is not reaching this installation.** Symptom: inbound calls and
   inbound SMS never arrive at all. On **Twilio**, re-run **Configure Selected** on
   the number in **Phone Management → Number Inventory**. On **Plivo** there is no
   per-number setting to fix — check instead that the number is assigned to the
   account you are testing with, and that `APP_URL` is the address Plivo can reach.
4. **The credentials never took.** On **Plivo**, **Admin → Telephony**: press the
   verify button and look for green. On **Twilio**, **Settings → Twilio**: there is
   no verify button, so confirm the page shows a connected account rather than an
   empty form.

If none of those explains it, capture, in this order:

- the step number and what you expected;
- exactly what happened instead, including anything spoken on the phone, word for
  word;
- the provider's call or message identifier;
- the provider console's request log for that call — **every** request it made to
  your server, with paths and response codes;
- the matching section of `storage/logs/laravel.log`, from a minute before to a
  minute after;
- the time, to the minute, and the timezone.

A failure reported with those six things can be diagnosed without your phone. One
without them usually cannot.

---

## 7. Results sheet

Copy this and fill it in as you go. "Ref" is the provider's call or message
identifier for that step.

**Installation**

| | |
|---|---|
| Date and timezone | |
| Path (A: two installations / B: one with reset) | |
| Address under test | |
| Test phone number | |
| Second calling device | |
| Release version | |

**Twilio pass**

| Step | What | Pass | Ref | Time | Notes |
|---|---|---|---|---|---|
| T1a | Softphone outbound, answered | | | | |
| T1b | One-contact TTS campaign | | | | |
| T2 | Inbound to the softphone | | | | |
| T3 | Recording stored and plays | | | | |
| T4 | SMS out, short and 3-part | | | | |
| T5 | SMS in | | | | |
| T6 | AI agent, two turns + silence | | | | |
| T7 | Queue: caller waits, agent joins | | | | |
| T8 | KYC: code, wrong, right, resend | | | | |

**Plivo pass**

| Step | What | Pass | Ref | Time | Notes |
|---|---|---|---|---|---|
| P1a | Softphone outbound, answered | | | | |
| P1b | One-contact TTS campaign | | | | |
| P2 | Inbound to the softphone | | | | |
| P3 | Recording stored and plays | | | | |
| P4 | SMS out, short and 3-part | | | | |
| P5 | SMS in | | | | |
| P6 | AI agent, two turns + silence | | | | |
| P7 | Queue: caller waits, agent joins | | | | |
| P8 | KYC: code, wrong, right, resend | | | | |
| P9 | Agent does not answer, caller returns | | | | |

**The ten questions**

| | Question | Confirmed / Refuted | What you observed |
|---|---|---|---|
| Q1 | Browser call's caller form | | |
| Q2 | Spoken input, and silence | | |
| Q3 | Status while ringing | | |
| Q4 | Abandoned while waiting | | |
| Q5 | Unanswered agent leg reports | | |
| Q6 | Hold document knows the caller | | |
| Q7 | Hold document's fetch is signed | | |
| Q8 | Completed queued call ends cleanly | | |
| Q9 | Caller's number accepted as source | | |
| Q10 | Hold document fetch count and music | | |

**Reset**

| Step | What | Pass | Notes |
|---|---|---|---|
| R3 | Console reset refuses: mismatch, then abort (while still locked) | | |
| R4 | Reset from the admin panel | | |
| R4 | Did it refuse first? Which message, and what you cleared | | |
| R4 | Console command afterwards says "nothing to reset" | | |
| R5 | Locked to the next provider | | |
| R6 | Only the intended data went | | |
| R7 | Re-locked to Twilio; call and SMS work | | |
| R8 | Numbers released at the provider | | |

**Sign-off**

| | |
|---|---|
| Run by | |
| Completed | |
| Fit for customer traffic (yes / no / with the exceptions below) | |
| Exceptions and things to report | |
