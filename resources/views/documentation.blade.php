<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Teleman') }} - Documentation</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-light: #eff6ff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg: #ffffff;
            --bg-alt: #f8fafc;
            --sidebar-width: 280px;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--text); line-height: 1.7; background: var(--bg); }

        /* Sidebar */
        .sidebar { position: fixed; top: 0; left: 0; width: var(--sidebar-width); height: 100vh; overflow-y: auto; background: var(--bg-alt); border-right: 1px solid var(--border); padding: 24px 0; z-index: 10; }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid var(--border); margin-bottom: 16px; }
        .sidebar-header h2 { font-size: 18px; color: var(--primary); }
        .sidebar-header p { font-size: 12px; color: var(--text-muted); }
        .sidebar nav a { display: block; padding: 6px 20px; color: var(--text-muted); text-decoration: none; font-size: 14px; transition: all .15s; }
        .sidebar nav a:hover { color: var(--primary); background: var(--primary-light); }
        .sidebar nav .section-title { font-weight: 600; color: var(--text); font-size: 13px; text-transform: uppercase; letter-spacing: .5px; padding: 16px 20px 6px; }
        .sidebar nav a.sub { padding-left: 36px; font-size: 13px; }

        /* Main */
        .main { margin-left: var(--sidebar-width); padding: 40px 60px 80px; max-width: 960px; }
        h1 { font-size: 32px; margin-bottom: 8px; color: var(--text); }
        h2 { font-size: 24px; margin-top: 48px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid var(--border); }
        h3 { font-size: 20px; margin-top: 32px; margin-bottom: 12px; }
        h4 { font-size: 16px; margin-top: 24px; margin-bottom: 8px; }
        p { margin-bottom: 12px; }
        .subtitle { color: var(--text-muted); font-size: 18px; margin-bottom: 32px; }
        ul, ol { margin: 0 0 16px 24px; }
        li { margin-bottom: 4px; }

        /* Components */
        table { width: 100%; border-collapse: collapse; margin: 16px 0 24px; font-size: 14px; }
        th, td { padding: 10px 14px; text-align: left; border: 1px solid var(--border); }
        th { background: var(--bg-alt); font-weight: 600; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
        .callout { padding: 16px 20px; border-radius: 8px; margin: 16px 0; border-left: 4px solid; }
        .callout-info { background: #eff6ff; border-color: var(--primary); }
        .callout-warning { background: #fffbeb; border-color: var(--warning); }
        .callout-success { background: #f0fdf4; border-color: var(--success); }
        .callout-danger { background: #fef2f2; border-color: var(--danger); }
        .callout strong { display: block; margin-bottom: 4px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; }
        .badge-admin { background: #fef3c7; color: #92400e; }
        .badge-user { background: #dbeafe; color: #1e40af; }
        .badge-new { background: #dcfce7; color: #166534; }
        .steps { counter-reset: step; list-style: none; margin-left: 0; }
        .steps li { counter-increment: step; padding: 8px 0 8px 40px; position: relative; }
        .steps li::before { content: counter(step); position: absolute; left: 0; top: 8px; width: 28px; height: 28px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; }

        /* Mobile */
        .menu-toggle { display: none; position: fixed; top: 16px; left: 16px; z-index: 20; background: var(--primary); color: white; border: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; }
        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .sidebar { transform: translateX(-100%); transition: transform .2s ease; }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; padding: 24px 20px; padding-top: 60px; }
        }
    </style>
</head>
<body>

<button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">Menu</button>

<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>{{ config('app.name', 'Teleman') }}</h2>
        <p>User Documentation</p>
    </div>
    <nav>
        <a href="#overview">Product Overview</a>
        <a href="#features">Key Features</a>

        <div class="section-title">Getting Started</div>
        <a href="#installation" class="sub">Installation</a>
        <a href="#first-login" class="sub">First Login</a>
        <a href="#setup-twilio" class="sub">Twilio Setup</a>
        <a href="#kyc" class="sub">KYC Verification</a>

        <div class="section-title">Core Features</div>
        <a href="#dashboard" class="sub">Dashboard</a>
        <a href="#contacts" class="sub">Contacts</a>
        <a href="#contact-lists" class="sub">Contact Lists</a>
        <a href="#contact-tags" class="sub">Contact Tags</a>
        <a href="#campaigns" class="sub">Campaigns</a>
        <a href="#sequences" class="sub">Sequences</a>
        <a href="#calls" class="sub">Calls & Softphone</a>
        <a href="#sms" class="sub">SMS</a>
        <a href="#ai-agents" class="sub">Voice AI Agents</a>
        <a href="#knowledge-base" class="sub">Knowledge Base</a>
        <a href="#audio-files" class="sub">Audio Files</a>
        <a href="#templates" class="sub">Campaign Templates</a>
        <a href="#analytics" class="sub">Analytics</a>

        <div class="section-title">Account</div>
        <a href="#phone-numbers" class="sub">Phone Numbers</a>
        <a href="#credits" class="sub">Credits & Billing</a>
        <a href="#team" class="sub">Team Management</a>
        <a href="#integrations" class="sub">CRM Integrations</a>
        <a href="#api-tokens" class="sub">API Tokens</a>
        <a href="#settings" class="sub">Settings</a>

        <div class="section-title">Administration</div>
        <a href="#admin-users" class="sub">User Management</a>
        <a href="#admin-roles" class="sub">Roles & Permissions</a>
        <a href="#admin-kyc" class="sub">KYC Review</a>
        <a href="#admin-numbers" class="sub">Number Inventory</a>
        <a href="#admin-sip" class="sub">SIP Trunking & BYOC</a>
        <a href="#admin-pricing" class="sub">Pricing Rules</a>
        <a href="#admin-credits" class="sub">Credit Management</a>
        <a href="#admin-profit" class="sub">Profit Dashboard</a>
        <a href="#admin-system" class="sub">System Configuration</a>
        <a href="#admin-theme" class="sub">Theme & Branding</a>
        <a href="#admin-languages" class="sub">Languages</a>
        <a href="#admin-logs" class="sub">Logs & Monitoring</a>

        <div class="section-title">Help</div>
        <a href="#faq" class="sub">FAQ</a>
        <a href="#troubleshooting" class="sub">Troubleshooting</a>
        <a href="#tips" class="sub">Tips & Best Practices</a>
        <a href="#support" class="sub">Support</a>
    </nav>
</aside>

<!-- Main Content -->
<main class="main">

<!-- ============================================ -->
<!-- PRODUCT OVERVIEW -->
<!-- ============================================ -->
<h1 id="overview">{{ config('app.name', 'Teleman') }} Documentation</h1>
<p class="subtitle">Complete guide to your AI-powered telemarketing & customer engagement platform</p>

<p><strong>{{ config('app.name', 'Teleman') }}</strong> is a complete telemarketing platform that helps businesses connect with customers through voice calls, SMS, and AI-powered conversations. Whether you are running automated calling campaigns, managing contacts, or deploying intelligent voice agents, this platform brings everything together in one place.</p>

<h3>Who Is This For?</h3>
<ul>
    <li><strong>Sales teams</strong> running outbound calling campaigns to reach prospects</li>
    <li><strong>Customer support teams</strong> handling inbound calls with AI assistance</li>
    <li><strong>Marketing teams</strong> sending voice and SMS campaigns to large contact lists</li>
    <li><strong>Business owners</strong> looking for an all-in-one telemarketing solution</li>
    <li><strong>Administrators</strong> managing users, billing, and system configuration</li>
</ul>

<h3>User Roles</h3>
<table>
    <tr><th>Role</th><th>What They Can Do</th></tr>
    <tr><td><span class="badge badge-admin">Admin</span></td><td>Full system access: manage users, pricing, billing, phone numbers, SIP trunking, system settings, branding, and all features below</td></tr>
    <tr><td><span class="badge badge-user">Customer</span></td><td>Create campaigns, manage contacts, make calls, send SMS, deploy AI agents, manage team agents, view analytics, manage integrations</td></tr>
    <tr><td><span class="badge badge-user">Agent</span></td><td>Make/receive calls via softphone, view assigned phone numbers, limited access set by their team owner</td></tr>
</table>

<h3>KYC Access Levels</h3>
<p>Certain features require KYC (Know Your Customer) verification before they become available:</p>
<table>
    <tr><th>KYC Level</th><th>Features Unlocked</th></tr>
    <tr><td><strong>No KYC</strong></td><td>Dashboard, Softphone, Settings, Profile management</td></tr>
    <tr><td><strong>Basic (Tier 1)</strong></td><td>Campaigns, Contacts, Calls, SMS, Analytics, Audio Files, Sequences, Integrations, Phone Numbers, Pricing Calculator</td></tr>
    <tr><td><strong>Business (Tier 2)</strong></td><td>AI Voice Agents, Knowledge Bases</td></tr>
</table>


<!-- ============================================ -->
<!-- KEY FEATURES -->
<!-- ============================================ -->
<h2 id="features">Key Features & Benefits</h2>

<table>
    <tr><th>Feature</th><th>What It Does</th><th>Benefit</th></tr>
    <tr><td><strong>Voice Campaigns</strong></td><td>Automated calling campaigns with Text-to-Speech or recorded audio</td><td>Reach thousands of contacts without manual dialing</td></tr>
    <tr><td><strong>Voice AI Agents</strong></td><td>AI-powered agents that handle live conversations autonomously</td><td>24/7 customer engagement without human agents</td></tr>
    <tr><td><strong>SMS Messaging</strong></td><td>Send and receive SMS/MMS with conversation tracking</td><td>Multi-channel communication from one platform</td></tr>
    <tr><td><strong>Softphone</strong></td><td>Browser-based calling from any device via WebRTC</td><td>No hardware needed; call from anywhere</td></tr>
    <tr><td><strong>Contact Management</strong></td><td>CRM-lite with tags, lists, bulk operations, and import/export</td><td>Organized contact database ready for campaigns</td></tr>
    <tr><td><strong>Follow-Up Sequences</strong></td><td>Automated multi-step follow-ups triggered by call outcomes</td><td>Never miss a follow-up; convert more leads</td></tr>
    <tr><td><strong>CRM Integrations</strong></td><td>Push data to external CRMs via webhooks</td><td>Keep your existing tools in sync automatically</td></tr>
    <tr><td><strong>Real-Time Analytics</strong></td><td>Campaign performance, call stats, hot leads, smart scheduling</td><td>Data-driven decisions to improve results</td></tr>
    <tr><td><strong>Knowledge Base</strong></td><td>Upload documents for AI agents to reference during calls</td><td>AI agents give accurate, context-aware answers</td></tr>
    <tr><td><strong>Multi-Language</strong></td><td>Full UI available in 10 languages</td><td>Use the platform in your preferred language</td></tr>
    <tr><td><strong>Credit System</strong></td><td>Pay-as-you-go billing with 4 payment gateways</td><td>Only pay for what you use</td></tr>
    <tr><td><strong>Team Management</strong></td><td>Add agents to your team with controlled permissions</td><td>Scale your team while maintaining control</td></tr>
    <tr><td><strong>SIP Trunking & BYOC</strong></td><td>Bring Your Own Carrier for voice services</td><td>Use your existing telephony provider with the platform</td></tr>
    <tr><td><strong>REST API</strong></td><td>Full API access with personal API tokens</td><td>Build custom integrations and automate workflows</td></tr>
    <tr><td><strong>Data Export</strong></td><td>Export contacts, calls, campaigns, and reports to CSV</td><td>Take your data wherever you need it</td></tr>
</table>


<!-- ============================================ -->
<!-- GETTING STARTED -->
<!-- ============================================ -->
<h2 id="installation">Getting Started: Installation</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>When you first access the application, an installation wizard guides you through the initial setup. The installer checks your server requirements and sets up everything automatically.</p>

<h3>Server Requirements</h3>
<table>
    <tr><th>Requirement</th><th>Minimum</th></tr>
    <tr><td>PHP</td><td>8.3 or higher</td></tr>
    <tr><td>Database</td><td>MySQL 8.0+ or MariaDB 10.5+</td></tr>
    <tr><td>Web Server</td><td>Apache 2.4+ or Nginx 1.18+</td></tr>
    <tr><td>Node.js</td><td>18.x or higher (for building frontend assets)</td></tr>
</table>

<h3>Required PHP Extensions</h3>
<p>PDO, PDO MySQL, mbstring, OpenSSL, Tokenizer, XML, Ctype, JSON, BCMath, Fileinfo, cURL</p>

<h3>Installation Steps</h3>

<h4>Step 1: Requirements Check</h4>
<p>The system verifies your server meets all minimum requirements: PHP version (8.3+), required extensions, directory permissions (<code>storage/</code>, <code>bootstrap/cache/</code>), and that the <code>.env</code> file exists. All items must show a green checkmark before proceeding.</p>

<h4>Step 2: Database Configuration</h4>
<p>Enter your MySQL database connection details: host, port, database name, username, and password. Click <strong>Test Connection</strong> to verify, then click <strong>Migrate Database</strong> to create all necessary tables and seed default data.</p>
<div class="callout callout-info">
    <strong>What gets created automatically</strong>
    The migration process creates 120+ database tables and seeds default data including: 3 user roles (Admin, Customer, Agent), 100+ permissions, theme defaults, campaign templates, pricing rules, and 10 language packs.
</div>

<h4>Step 3: Create Admin Account</h4>
<p>Set up the first administrator account with your name, email, and a strong password (minimum 8 characters). This account has full system access to configure everything else.</p>

<h4>Step 4: Complete</h4>
<p>Setup is finished! Click the login button to access your new platform. The installer creates a <code>.installed</code> marker file, so the wizard won't appear again.</p>

<div class="callout callout-warning">
    <strong>Important: Background Processes</strong>
    After installation, you must set up queue workers and a scheduler for campaigns and background jobs to run. Ask your server administrator to configure these. Without them, campaigns will not execute and background tasks will not process.
</div>


<h2 id="first-login">First Login & Initial Setup</h2>

<p>After logging in for the first time, follow these steps to get your platform ready:</p>

<ol class="steps">
    <li><strong>Configure Twilio</strong> &mdash; Go to <strong>Settings &rarr; Twilio</strong> and enter your Twilio Account SID and Auth Token. This enables voice calling and SMS.</li>
    <li><strong>Set Up Payment Gateways</strong> &mdash; Go to <strong>Admin &rarr; System Configuration</strong> and configure at least one payment gateway (Stripe, Razorpay, PayPal, or Paystack) so customers can purchase credits.</li>
    <li><strong>Configure Email</strong> &mdash; In <strong>System Configuration</strong>, set up SMTP email settings for password resets, notifications, and KYC updates. Use the <strong>Send Test Email</strong> button to verify.</li>
    <li><strong>Set Up Pricing</strong> &mdash; Go to <strong>Admin &rarr; Pricing</strong> and configure your markup rates for calls, SMS, and phone numbers.</li>
    <li><strong>Add Phone Numbers</strong> &mdash; Go to <strong>Admin &rarr; Phone Numbers</strong>, click <strong>Sync from Twilio</strong> to import available numbers, then assign them to users.</li>
    <li><strong>Complete KYC</strong> &mdash; Go to <strong>Settings &rarr; KYC Verification</strong> and submit your verification to unlock all platform features.</li>
    <li><strong>Top Up Credits</strong> &mdash; Go to <strong>Credits &rarr; Top Up</strong> and purchase credits to start making calls.</li>
    <li><strong>Add Contacts</strong> &mdash; Go to <strong>Contacts</strong> and import your contact list via CSV or add contacts manually.</li>
    <li><strong>Launch Your First Campaign</strong> &mdash; Go to <strong>Campaigns &rarr; Create</strong> and set up your first calling campaign!</li>
</ol>


<h2 id="setup-twilio">Setting Up Twilio</h2>

<p>Twilio is the telephony provider that powers all voice calls and SMS in the platform. You need a Twilio account to use these features.</p>

<ol class="steps">
    <li>Sign up at <strong>twilio.com</strong> if you don't have an account.</li>
    <li>From your Twilio Console, copy your <strong>Account SID</strong> and <strong>Auth Token</strong>.</li>
    <li>In the platform, go to <strong>Settings &rarr; Twilio</strong>.</li>
    <li>Paste your Account SID and Auth Token into the fields.</li>
    <li>Click <strong>Save</strong>. The system will verify your credentials.</li>
</ol>

<div class="callout callout-info">
    <strong>Note</strong>
    Twilio credentials are configured once by the admin in the System Configuration. Individual customer users do not need their own Twilio accounts &mdash; they use phone numbers assigned to them by the admin.
</div>

<h3>Geo Permissions</h3>
<p>Admins can control which countries users are allowed to call or send SMS to. Go to <strong>Settings &rarr; Geo Permissions</strong> to enable or disable specific countries. This helps control costs and comply with regulations.</p>


<h2 id="kyc">KYC Verification</h2>

<p>KYC (Know Your Customer) verification helps comply with regulations and progressively unlocks platform features for your account.</p>

<h4>Basic Verification (Tier 1)</h4>
<p>Basic verification unlocks core features: Campaigns, Contacts, Calls, SMS, Analytics, and more.</p>
<ol class="steps">
    <li>Go to <strong>Settings &rarr; KYC Verification</strong>.</li>
    <li>Click <strong>Start Basic Verification</strong>.</li>
    <li>Enter your phone number and verify via the OTP code sent to you.</li>
    <li>Once verified, your account is upgraded to Basic tier and core features are unlocked.</li>
</ol>

<h4>Business Verification (Tier 2)</h4>
<p>Business verification unlocks advanced features: AI Voice Agents and Knowledge Bases.</p>
<ol class="steps">
    <li>Go to <strong>Settings &rarr; KYC Verification &rarr; Business</strong>.</li>
    <li>Fill in your business name, registration number, business type, and address.</li>
    <li>Upload the required documents: ID Document, Business Registration, Selfie with ID.</li>
    <li>Click <strong>Submit for Review</strong>. An admin will review your submission.</li>
    <li>You will be notified when your verification is approved or if additional information is needed.</li>
</ol>


<!-- ============================================ -->
<!-- CORE FEATURES -->
<!-- ============================================ -->
<h2 id="dashboard">Dashboard</h2>

<p>The Dashboard is your home screen after logging in. It provides a quick overview of your account activity:</p>
<ul>
    <li><strong>Statistics Cards</strong> &mdash; Total campaigns, total calls, answer rate, total call duration</li>
    <li><strong>Recent Campaigns</strong> &mdash; Your latest campaigns with status and progress</li>
    <li><strong>Credit Balance</strong> &mdash; Current balance displayed in the navigation bar</li>
    <li><strong>Quick Actions</strong> &mdash; Shortcuts to create campaigns, add contacts, and make calls</li>
</ul>


<h2 id="contacts">Contacts</h2>

<p>The Contacts section is your centralized database of all the people you want to reach. Think of it as a lightweight CRM built into the platform.</p>

<h3>Adding a Single Contact</h3>
<ol class="steps">
    <li>Go to <strong>Contacts</strong> and click <strong>Add Contact</strong>.</li>
    <li>Fill in the required fields: <strong>First Name</strong>, <strong>Last Name</strong>, and <strong>Phone Number</strong> (in international format, e.g., +14155551234).</li>
    <li>Optionally add <strong>Email</strong>, <strong>Company</strong>, and <strong>Notes</strong>.</li>
    <li>Assign the contact to one or more <strong>Contact Lists</strong> and apply <strong>Tags</strong> for organization.</li>
    <li>Click <strong>Save</strong>.</li>
</ol>

<h3>Importing Contacts from CSV</h3>
<ol class="steps">
    <li>Go to <strong>Contacts</strong> and click <strong>Import</strong>.</li>
    <li>Download the sample CSV template to see the expected format.</li>
    <li>Prepare your file with columns: <code>first_name</code>, <code>last_name</code>, <code>phone_number</code>, <code>email</code>, <code>company</code>.</li>
    <li>Upload your CSV file using drag-and-drop or the file browser.</li>
    <li>Map your CSV columns to the contact fields and select a contact list.</li>
    <li>Click <strong>Import</strong>. The import runs in the background &mdash; you can continue using the platform while it processes.</li>
</ol>

<h3>Bulk Operations</h3>
<p>Select multiple contacts using the checkboxes to perform bulk actions:</p>
<ul>
    <li><strong>Bulk Tag</strong> &mdash; Apply one or more tags to all selected contacts</li>
    <li><strong>Bulk Add to List</strong> &mdash; Add selected contacts to a contact list</li>
    <li><strong>Bulk Delete</strong> &mdash; Remove selected contacts permanently</li>
</ul>

<h3>Exporting Contacts</h3>
<p>Click the <strong>Export</strong> button to download all your contacts as a CSV file. You can use this to back up your data or import it into other systems.</p>

<h3>AI Data Quality Check</h3>
<p>Use the <strong>AI Quality Check</strong> tool to automatically scan your contacts for issues like invalid phone number formats, duplicates, and missing data. The system highlights problems and suggests fixes.</p>

<h3>Contact Details</h3>
<p>Click on any contact to view their full profile, including their complete call history, campaign interactions, tags, and notes. You can edit or delete contacts from this view.</p>


<h2 id="contact-lists">Contact Lists</h2>

<p>Contact Lists let you group related contacts together for easy campaign targeting. For example, you might have lists like "Q1 Leads", "Existing Customers", or "Event Attendees".</p>

<ol class="steps">
    <li>Go to <strong>Contact Lists</strong> and click <strong>Create List</strong>.</li>
    <li>Enter a <strong>Name</strong> and optional <strong>Description</strong>.</li>
    <li>Click <strong>Save</strong>.</li>
    <li>Add contacts to the list from the Contacts page, during import, or from the list detail view.</li>
</ol>

<div class="callout callout-info">
    <strong>Tip</strong>
    A single contact can belong to multiple lists. When you delete a list, the contacts themselves are not deleted &mdash; they remain in your database.
</div>


<h2 id="contact-tags">Contact Tags</h2>

<p>Tags provide a flexible way to categorize and filter contacts across all your lists. Unlike lists, tags are lightweight labels you can apply to any contact.</p>

<ol class="steps">
    <li>Go to <strong>Contact Tags</strong> and click <strong>Create Tag</strong>.</li>
    <li>Enter a <strong>Name</strong> and choose a <strong>Color</strong> for visual identification.</li>
    <li>Click <strong>Save</strong>.</li>
</ol>

<p>Apply tags to contacts individually from their profile, during CSV import, or in bulk by selecting multiple contacts and using <strong>Bulk Tag</strong>. When creating campaigns, you can filter contacts by tag to target specific segments.</p>


<h2 id="campaigns">Campaigns</h2>

<p>Campaigns are the core of the platform. They let you automatically call a list of contacts with a pre-defined message or audio file.</p>

<h3>Campaign Types</h3>
<table>
    <tr><th>Type</th><th>How It Works</th><th>Best For</th></tr>
    <tr><td><strong>Text-to-Speech (TTS)</strong></td><td>You write a script and the system converts it to a natural-sounding voice</td><td>Announcements, reminders, surveys</td></tr>
    <tr><td><strong>Voice-to-Voice</strong></td><td>You upload a pre-recorded audio file that plays during the call</td><td>Professional recordings, complex messages</td></tr>
</table>

<h3>Creating a Campaign</h3>
<ol class="steps">
    <li>Go to <strong>Campaigns</strong> and click <strong>Create Campaign</strong>.</li>
    <li>Enter a <strong>Campaign Name</strong> to identify it.</li>
    <li>Choose your <strong>Campaign Type</strong> (TTS or Voice-to-Voice).</li>
    <li>Select a <strong>Phone Number</strong> to call from (from your assigned numbers).</li>
    <li>For TTS: Write your message script. You can use variables like <code>@{{first_name}}</code> to personalize each call. Use the <strong>AI Message Generator</strong> to create compelling scripts automatically. For Voice-to-Voice: Select an uploaded audio file from your library.</li>
    <li>Select one or more <strong>Contact Lists</strong> to target.</li>
    <li>Configure optional settings:
        <ul>
            <li><strong>Call Recording</strong> &mdash; Record calls for quality assurance</li>
            <li><strong>DTMF Responses</strong> &mdash; Enable keypress detection and define actions for each key (e.g., "Press 1 to speak with an agent")</li>
            <li><strong>AMD</strong> &mdash; Answering Machine Detection to handle voicemail</li>
            <li><strong>Retry Attempts</strong> &mdash; Number of times to retry failed calls</li>
            <li><strong>Concurrent Call Limit</strong> &mdash; Maximum simultaneous calls</li>
        </ul>
    </li>
    <li>Choose your launch option: <strong>Save as Draft</strong>, <strong>Launch Now</strong>, or <strong>Schedule</strong> for a specific date and time.</li>
</ol>

<h3>Campaign Controls</h3>
<table>
    <tr><th>Action</th><th>When Available</th><th>What It Does</th></tr>
    <tr><td>Launch</td><td>Draft or Scheduled</td><td>Starts calling contacts immediately</td></tr>
    <tr><td>Pause</td><td>Running</td><td>Temporarily stops all calls; resume anytime</td></tr>
    <tr><td>Resume</td><td>Paused</td><td>Continues calling remaining contacts</td></tr>
    <tr><td>On-Demand Call</td><td>Any active campaign</td><td>Calls the next pending contact immediately (great for testing)</td></tr>
    <tr><td>Save as Template</td><td>Any campaign</td><td>Save the entire configuration for reuse</td></tr>
    <tr><td>Export</td><td>Any campaign</td><td>Download campaign calls or contacts as CSV</td></tr>
</table>

<h3>Campaign Analytics</h3>
<p>From the campaign detail page, you can see real-time stats including: total contacts, calls made, answer rate, failed calls, average duration, and a progress bar showing completion percentage. If DTMF is enabled, you can also see keypress analytics showing how many callers pressed each key.</p>

<h3>Using Variables in Messages</h3>
<p>Personalize your campaign messages by inserting contact-specific variables:</p>
<ul>
    <li><code>@{{first_name}}</code> &mdash; Contact's first name</li>
    <li><code>@{{last_name}}</code> &mdash; Contact's last name</li>
    <li><code>@{{company}}</code> &mdash; Contact's company name</li>
    <li><code>@{{phone_number}}</code> &mdash; Contact's phone number</li>
</ul>
<p>Example: <em>"Hello @{{first_name}}, this is a reminder from Acme Corp about your appointment tomorrow."</em></p>


<h2 id="sequences">Sequences (Automated Follow-Ups)</h2>

<p>Sequences let you create automated follow-up workflows that trigger based on campaign call outcomes. When a contact doesn't answer, shows interest, or requests a callback, the system can automatically follow up with calls, SMS, or emails on a schedule you define.</p>

<h3>Creating a Sequence</h3>
<ol class="steps">
    <li>Go to <strong>Sequences</strong> and click <strong>Create Sequence</strong>.</li>
    <li>Enter a <strong>Name</strong> and <strong>Description</strong>.</li>
    <li>Choose a <strong>Trigger Type</strong> that determines when contacts are enrolled:
        <ul>
            <li><strong>No Answer</strong> &mdash; Contact didn't pick up</li>
            <li><strong>Interested</strong> &mdash; Contact showed positive response</li>
            <li><strong>Callback Requested</strong> &mdash; Contact asked to be called back</li>
            <li><strong>Not Interested</strong> &mdash; For nurture sequences</li>
            <li><strong>Completed</strong> &mdash; For post-call follow-ups</li>
            <li><strong>Manual</strong> &mdash; You add contacts yourself</li>
        </ul>
    </li>
    <li>Add <strong>Steps</strong> to your sequence. Each step has:
        <ul>
            <li><strong>Delay</strong> &mdash; How long to wait (minutes, hours, or days)</li>
            <li><strong>Action</strong> &mdash; What to do: Call, SMS, Email, Wait, or Webhook</li>
            <li><strong>Content</strong> &mdash; The message or configuration for that action</li>
        </ul>
    </li>
    <li>Set a <strong>Priority</strong> (1-10) for ordering when multiple sequences match.</li>
    <li>Toggle <strong>Activate</strong> to start enrolling contacts automatically.</li>
</ol>

<h3>Message Variants (A/B Testing)</h3>
<p>Within each sequence step, you can create multiple message variants. The system will distribute contacts across variants and track which version performs best, helping you optimize your follow-up messages over time.</p>

<h3>Example Sequence: Missed Call Follow-Up</h3>
<table>
    <tr><th>Step</th><th>Delay</th><th>Action</th><th>Content</th></tr>
    <tr><td>1</td><td>4 hours</td><td>SMS</td><td>"Hi, we tried to reach you earlier. Please call us back at..."</td></tr>
    <tr><td>2</td><td>1 day</td><td>Call</td><td>Retry the original campaign call</td></tr>
    <tr><td>3</td><td>3 days</td><td>Email</td><td>Follow-up email with offer details</td></tr>
</table>


<h2 id="calls">Calls & Softphone</h2>

<h3>Call History</h3>
<p>The Calls page shows a complete log of all your calls &mdash; both from campaigns and manual calls. You can filter by:</p>
<ul>
    <li><strong>Status</strong>: Completed, Failed, No Answer, Busy, In Progress</li>
    <li><strong>Type</strong>: Campaign or Manual</li>
    <li><strong>Direction</strong>: Inbound or Outbound</li>
    <li><strong>Date Range</strong>: Filter by specific time period</li>
</ul>

<p>Each call record shows the phone numbers involved, duration, cost, recording (if enabled), and a link to the associated campaign or contact.</p>

<h3>Exporting Call Data</h3>
<p>Click the <strong>Export</strong> button on the Calls page to download your call history as a CSV file. You can also export calls for a specific campaign from the campaign detail page.</p>

<h3>Listening to Recordings</h3>
<p>If call recording was enabled, you will see a play button next to the call. Click it to listen directly in your browser. Recordings are stored securely and can be downloaded for offline review.</p>

<h3>Softphone (Browser Calling)</h3>
<p>The softphone lets you make and receive calls directly from your web browser without any additional software or hardware. It uses WebRTC technology for high-quality audio.</p>
<ol class="steps">
    <li>Click the <strong>phone icon</strong> in the navigation bar to open the softphone widget.</li>
    <li>Select which phone number you want to call from (if you have multiple assigned numbers).</li>
    <li>Dial a number using the keypad or type it directly.</li>
    <li>During a call, you can: <strong>Mute/Unmute</strong>, <strong>Hold</strong>, <strong>Record</strong>, use the <strong>DTMF keypad</strong>, or <strong>End</strong> the call.</li>
</ol>

<div class="callout callout-info">
    <strong>Tip</strong>
    For the best softphone experience, use Google Chrome and allow microphone access when prompted. Ensure you have a stable internet connection for clear audio quality.
</div>


<h2 id="sms">SMS Messaging</h2>

<p>Send and receive text messages through your assigned phone numbers.</p>

<h3>Sending an SMS</h3>
<ol class="steps">
    <li>Go to <strong>SMS</strong> and click <strong>Compose</strong>.</li>
    <li>Select a <strong>From Number</strong> (must be SMS-capable).</li>
    <li>Enter the recipient's phone number or select from your contacts.</li>
    <li>Type your message or choose from a saved template.</li>
    <li>Click <strong>Send</strong>.</li>
</ol>

<h3>Conversations</h3>
<p>The <strong>Conversations</strong> tab shows a chat-style view of all SMS exchanges organized by phone number. You can reply directly from this view, making it easy to have back-and-forth conversations with contacts.</p>

<h3>AI SMS Responses</h3>
<p>When enabled, the system can automatically generate intelligent replies to incoming SMS messages using AI. This allows you to maintain conversations even when you're not actively monitoring messages.</p>

<h3>SMS Templates</h3>
<p>Save frequently used messages as templates for quick access. Go to <strong>SMS &rarr; Templates</strong> to create and manage them. Templates can include variables for personalization.</p>

<h3>SMS Analytics</h3>
<p>View SMS sending statistics including delivery rates, response rates, and message counts over time.</p>


<h2 id="ai-agents">Voice AI Agents</h2>
<p><span class="badge badge-new">Requires Business KYC</span></p>

<p>Voice AI Agents are intelligent virtual assistants that can handle phone conversations autonomously. They use advanced language models to understand callers and respond naturally, without requiring a human on the line.</p>

<h3>How It Works</h3>
<ul>
    <li>The AI agent answers or makes calls on the assigned phone number</li>
    <li>Speech recognition (powered by Deepgram) converts the caller's speech to text in real time</li>
    <li>An AI language model generates a natural response based on your system prompt and knowledge base</li>
    <li>The response is spoken back using a human-like voice (Amazon Polly Generative or Google Chirp3-HD)</li>
    <li>The entire conversation happens in real time with sub-second latency</li>
</ul>

<h3>Creating an AI Agent</h3>
<ol class="steps">
    <li>Go to <strong>AI Agents</strong> and click <strong>Create Agent</strong>.</li>
    <li>Fill in <strong>Basic Information</strong>: Name, Description, and Type (Inbound, Outbound, or Both).</li>
    <li>Write a <strong>System Prompt</strong> &mdash; this is the instruction set that tells the AI how to behave. For example: <em>"You are a friendly customer service agent for Acme Corp. Help callers with order status, returns, and general questions. Always be polite and concise."</em> You can also select from pre-built templates (Support, Sales, Survey).</li>
    <li>Set the <strong>First Message</strong> &mdash; what the AI says when it answers or the person picks up.</li>
    <li>Set the <strong>Goodbye Message</strong> &mdash; what the AI says before hanging up.</li>
    <li>Choose an <strong>AI Model</strong> &mdash; select based on speed and capability (see table below).</li>
    <li>Choose a <strong>Voice</strong> &mdash; select from 10 natural-sounding voices (see table below).</li>
    <li>Optionally link a <strong>Knowledge Base</strong> to give the AI access to your company information.</li>
    <li>Configure call settings: max duration, silence timeout, response timeout, recording, and transfer rules.</li>
    <li>Click <strong>Create Agent</strong>.</li>
</ol>

<h3>Agent Types</h3>
<table>
    <tr><th>Type</th><th>Description</th><th>Use Case</th></tr>
    <tr><td><strong>Inbound</strong></td><td>Answers incoming calls to the assigned number</td><td>Customer support, appointment booking, FAQ handling</td></tr>
    <tr><td><strong>Outbound</strong></td><td>Makes calls to contacts you specify</td><td>Lead qualification, follow-ups, appointment confirmations</td></tr>
    <tr><td><strong>Both</strong></td><td>Handles both incoming and outgoing calls</td><td>Full-service virtual agent</td></tr>
</table>

<h3>Available AI Models</h3>
<table>
    <tr><th>Model</th><th>Speed</th><th>Cost</th><th>Best For</th></tr>
    <tr><td>Polaris Alpha</td><td>Ultra Fast (~1s)</td><td>Free</td><td>Quick, responsive phone conversations</td></tr>
    <tr><td>GPT OSS 20B</td><td>Very Fast (~1-2s)</td><td>Free</td><td>Good balance of speed and quality</td></tr>
    <tr><td>DeepSeek Chat v3.1</td><td>Fast (~2-3s)</td><td>Free</td><td>Complex conversations, longer context</td></tr>
    <tr><td>DeepSeek Chat v3-0324</td><td>Fast (~2-3s)</td><td>Free</td><td>Complex conversations, longer context</td></tr>
    <tr><td>GLM 4.5 Air</td><td>Fast (~2-4s)</td><td>Free</td><td>Long-context conversations (128K tokens)</td></tr>
    <tr><td>Kimi K2</td><td>Fast (~2-4s)</td><td>Free</td><td>Detailed reasoning tasks (128K tokens)</td></tr>
</table>
<div class="callout callout-success">
    <strong>Cost-Effective</strong>
    All recommended models are free to use. The main costs are Twilio voice minutes and text-to-speech usage, resulting in approximately $0.015/minute total &mdash; 95% less than comparable services.
</div>

<h3>Available Voices</h3>
<table>
    <tr><th>Voice</th><th>Engine</th><th>Gender</th><th>Style</th></tr>
    <tr><td>Joanna</td><td>Amazon Polly</td><td>Female</td><td>Warm & natural (default)</td></tr>
    <tr><td>Matthew</td><td>Amazon Polly</td><td>Male</td><td>Confident & clear</td></tr>
    <tr><td>Ruth</td><td>Amazon Polly</td><td>Female</td><td>Professional & composed</td></tr>
    <tr><td>Stephen</td><td>Amazon Polly</td><td>Male</td><td>Authoritative & trustworthy</td></tr>
    <tr><td>Aoede</td><td>Google Chirp3-HD</td><td>Female</td><td>Bright & expressive</td></tr>
    <tr><td>Charon</td><td>Google Chirp3-HD</td><td>Male</td><td>Deep & composed</td></tr>
    <tr><td>Kore</td><td>Google Chirp3-HD</td><td>Female</td><td>Warm & friendly</td></tr>
    <tr><td>Fenrir</td><td>Google Chirp3-HD</td><td>Male</td><td>Energetic & dynamic</td></tr>
    <tr><td>Puck</td><td>Google Chirp3-HD</td><td>Male</td><td>Playful & engaging</td></tr>
    <tr><td>Leda</td><td>Google Chirp3-HD</td><td>Female</td><td>Calm & soothing</td></tr>
</table>

<h3>Call Transfer</h3>
<p>AI agents can transfer calls to a human operator when specific keywords or phrases are mentioned:</p>
<ol class="steps">
    <li>Enable <strong>Call Transfer</strong> in the agent settings.</li>
    <li>Enter the <strong>Transfer Number</strong> (the human agent's phone number).</li>
    <li>Add <strong>Trigger Keywords</strong> like "speak to a person", "transfer me", "human agent".</li>
    <li>When a caller says any of these phrases, the AI will announce the transfer and connect the caller automatically.</li>
</ol>

<h3>Testing Your Agent</h3>
<p>Before going live, test your agent's configuration:</p>
<ol class="steps">
    <li>Open the agent detail page and click <strong>Test Configuration</strong>.</li>
    <li>The system checks: AI model connectivity (OpenRouter), Twilio integration, and webhook URL accessibility.</li>
    <li>Fix any issues shown in red before activating the agent.</li>
    <li>Make a test call to your agent's phone number to verify the conversation flow.</li>
</ol>

<h3>Monitoring Live Calls</h3>
<p>Go to <strong>AI Agents &rarr; Live</strong> to see all active AI agent calls in real time. You can view the live transcript, call duration, turn count, and current status. You can also end calls from this view if needed.</p>

<h3>Call History & Transcripts</h3>
<p>Go to <strong>AI Agents &rarr; [Agent Name]</strong> to view the agent's complete call history including full transcripts, cost breakdown (Twilio + LLM costs), turn count, duration, and sentiment analysis. Use the <strong>Export</strong> button to download the history as CSV.</p>


<h2 id="knowledge-base">Knowledge Base</h2>
<p><span class="badge badge-new">Requires Business KYC</span></p>

<p>The Knowledge Base stores information that your AI agents can reference during conversations. When a caller asks a question, the AI checks the knowledge base for relevant information before responding, ensuring accurate and consistent answers.</p>

<ol class="steps">
    <li>Go to <strong>Knowledge Bases</strong> and click <strong>Create</strong>.</li>
    <li>Enter a <strong>Title</strong> and paste your content (company info, FAQs, product details, pricing, policies, etc.).</li>
    <li>Click <strong>Save</strong>.</li>
    <li>When creating or editing an AI agent, select this knowledge base from the dropdown.</li>
</ol>

<div class="callout callout-info">
    <strong>Tip</strong>
    Keep your knowledge base content concise and well-organized. Use clear headings and bullet points. The system uses up to 4,000 characters from your knowledge base in each conversation for the best balance of context and response speed.
</div>

<h3>Best Practices for Knowledge Base Content</h3>
<ul>
    <li>Start with the most frequently asked questions and their answers</li>
    <li>Include key business information: hours, location, contact details, policies</li>
    <li>Use a Q&A format for the clearest results</li>
    <li>Update the knowledge base whenever your business information changes</li>
    <li>You can create multiple knowledge bases for different AI agents or topics</li>
</ul>


<h2 id="audio-files">Audio Files</h2>

<p>Upload pre-recorded audio files to use in Voice-to-Voice campaigns.</p>

<ol class="steps">
    <li>Go to <strong>Audio Files</strong> and click <strong>Upload</strong>.</li>
    <li>Drag and drop your audio file or click to browse. Supported formats: MP3, WAV, OGG, M4A (max 10MB).</li>
    <li>Add an optional description.</li>
    <li>Click <strong>Upload</strong>. The file will appear in your library with duration and size.</li>
    <li>Use the play button to preview any file before using it in a campaign.</li>
</ol>


<h2 id="templates">Campaign Templates</h2>

<p>Campaign Templates save you time by storing pre-configured campaign setups that you can reuse.</p>

<h3>Saving a Template</h3>
<p>From any campaign detail page, click <strong>Save as Template</strong>. Enter a name and description, and the entire campaign configuration (message, settings, DTMF responses) is saved for future use.</p>

<h3>Using a Template</h3>
<p>When creating a new campaign, click <strong>Use Template</strong> and select from your saved templates or the pre-built system templates. All settings are pre-filled and you can adjust as needed before launching.</p>

<h3>Template Categories</h3>
<table>
    <tr><th>Category</th><th>Description</th><th>Example</th></tr>
    <tr><td><strong>Promotional</strong></td><td>Sales offers, discounts, new products</td><td>"Hi, we have an exclusive 30% off deal..."</td></tr>
    <tr><td><strong>Notification</strong></td><td>Appointment reminders, alerts</td><td>"This is a reminder about your appointment tomorrow..."</td></tr>
    <tr><td><strong>Survey</strong></td><td>Customer feedback, polls with DTMF</td><td>"Please rate your experience: Press 1 for great..."</td></tr>
    <tr><td><strong>Lead Qualification</strong></td><td>Initial outreach, interest assessment</td><td>"We noticed you showed interest in our product..."</td></tr>
    <tr><td><strong>Personalized</strong></td><td>Custom one-to-one messages</td><td>"Hi @{{first_name}}, we wanted to follow up..."</td></tr>
    <tr><td><strong>General</strong></td><td>Other use cases</td><td>Any custom message template</td></tr>
</table>


<h2 id="analytics">Analytics</h2>

<p>The Analytics section gives you a bird's-eye view of your campaign and calling performance with visual charts and actionable insights.</p>

<h3>Dashboard Overview</h3>
<p>View key metrics at a glance:</p>
<ul>
    <li><strong>Total Campaigns</strong> with active/completed/draft breakdown</li>
    <li><strong>Total Calls</strong> with answered count and answer rate</li>
    <li><strong>Average Answer Rate</strong> across all campaigns</li>
    <li><strong>Total Call Duration</strong> in minutes/hours</li>
    <li><strong>Campaign Performance Charts</strong> showing trends over time</li>
</ul>

<h3>Hot Leads</h3>
<p>The <strong>Hot Leads</strong> page automatically identifies contacts who showed high interest based on call outcomes, DTMF responses (e.g., pressing "1" for interest), long call durations, and AI sentiment analysis. These contacts are ranked by lead quality score, making it easy to prioritize your follow-up efforts.</p>

<h3>Smart Scheduling</h3>
<p>The <strong>Smart Scheduling</strong> page analyzes your historical call data to recommend the best times to reach contacts. It considers time zones, past answer rates by hour and day of week, and contact-specific patterns to help you improve your campaign timing.</p>

<h3>Sentiment Analysis</h3>
<p>For calls with recordings or transcripts, AI-powered sentiment analysis automatically classifies the call outcome as positive, neutral, or negative. This data feeds into the Hot Leads identification and helps you understand the overall tone of your campaigns.</p>

<h3>Exporting Analytics</h3>
<p>Export campaign analytics data as CSV for offline analysis or reporting. Available from the campaign detail page and the analytics overview.</p>


<!-- ============================================ -->
<!-- ACCOUNT -->
<!-- ============================================ -->
<h2 id="phone-numbers">Phone Numbers</h2>

<h3>Viewing Your Numbers</h3>
<p>Go to <strong>Numbers &rarr; My Numbers</strong> to see all phone numbers assigned to your account. Each number shows its capabilities (Voice, SMS, MMS), country, and monthly cost.</p>

<h3>Requesting a New Number</h3>
<ol class="steps">
    <li>Go to <strong>Numbers &rarr; Available Numbers</strong>.</li>
    <li>Search by country and optional area code.</li>
    <li>Browse available numbers and click <strong>Request</strong> on the one you want.</li>
    <li>Track your request status in <strong>Numbers &rarr; My Requests</strong>.</li>
    <li>An admin will review and approve or reject your request. You will be notified of the outcome.</li>
</ol>

<h3>Pricing Calculator</h3>
<p>Use the <strong>Pricing Calculator</strong> to estimate costs before making a request. Enter a country to see the monthly phone number fee and per-minute call rates, including any markup applied by your admin.</p>


<h2 id="credits">Credits & Billing</h2>

<p>The platform uses a credit-based billing system. You purchase credits and they are deducted as you make calls, send SMS, or rent phone numbers.</p>

<h3>How Credits Work</h3>
<table>
    <tr><th>Activity</th><th>How It's Charged</th></tr>
    <tr><td>Voice Calls</td><td>Per minute, based on destination country + admin markup</td></tr>
    <tr><td>SMS Messages</td><td>Per segment (160 characters), based on destination + markup</td></tr>
    <tr><td>Phone Number Rental</td><td>Monthly fee per number, charged on the 1st of each month</td></tr>
    <tr><td>AI Agent Calls</td><td>Per minute (Twilio voice) + AI model usage cost</td></tr>
</table>

<h3>Checking Your Balance</h3>
<p>Your current credit balance is always visible in the top navigation bar. For a detailed breakdown, go to <strong>Credits</strong>.</p>

<h3>Topping Up Credits</h3>
<ol class="steps">
    <li>Go to <strong>Credits &rarr; Top Up</strong>.</li>
    <li>Select a credit amount or enter a custom amount.</li>
    <li>Choose your payment method:</li>
</ol>
<table>
    <tr><th>Gateway</th><th>Supported Currencies</th></tr>
    <tr><td><strong>Stripe</strong></td><td>USD, EUR, GBP, AUD, CAD, JPY, INR, and 13 more</td></tr>
    <tr><td><strong>Razorpay</strong></td><td>INR, USD, EUR, GBP, AED, SGD, and 14 more</td></tr>
    <tr><td><strong>PayPal</strong></td><td>USD, EUR, GBP, AUD, CAD, and 20 more</td></tr>
    <tr><td><strong>Paystack</strong></td><td>NGN, GHS, ZAR, KES, USD</td></tr>
</table>
<p>Complete the payment through the secure checkout. Credits are added to your account instantly upon successful payment.</p>

<h3>Transaction History</h3>
<p>Go to <strong>Credits &rarr; History</strong> to view all credit transactions including top-ups, call charges, SMS charges, and phone number fees. Click <strong>Export</strong> to download the history as CSV.</p>


<h2 id="team">Team Management (Agents)</h2>

<p>If you manage a team of callers, you can add them as "Agents" under your account.</p>

<ol class="steps">
    <li>Go to <strong>Agents</strong> and click <strong>Add Agent</strong>.</li>
    <li>Enter their name, email, and set a password.</li>
    <li>Assign phone numbers to the agent (they can only use numbers you assign).</li>
    <li>Set their permissions (what features they can access).</li>
    <li>Click <strong>Save</strong>. The agent can now log in with their credentials.</li>
</ol>

<p>You can view each agent's performance stats (calls made, duration, answer rate) from their profile page. Agents can be activated, deactivated, or removed at any time.</p>


<h2 id="integrations">CRM Integrations</h2>

<p>Connect your platform to external CRMs and tools to automatically push call data, leads, and contact updates via webhooks.</p>

<h3>Setting Up an Integration</h3>
<ol class="steps">
    <li>Go to <strong>Integrations</strong> and click <strong>Create Integration</strong>.</li>
    <li>Enter a <strong>Name</strong> for the integration (e.g., "HubSpot CRM", "Salesforce").</li>
    <li>Enter the <strong>Webhook URL</strong> where data should be sent (provided by your CRM or tool).</li>
    <li>Select the <strong>Events</strong> you want to subscribe to:
        <ul>
            <li>Call Completed</li>
            <li>Lead Qualified</li>
            <li>Contact Added</li>
            <li>Contact Updated</li>
            <li>Campaign Started</li>
            <li>DTMF Response Received</li>
        </ul>
    </li>
    <li>Choose an <strong>Authentication Method</strong>: Bearer Token, API Key, HMAC Signature, or None.</li>
    <li>Enter the authentication credentials.</li>
    <li>Click <strong>Test</strong> to send a sample payload and verify the connection.</li>
    <li>Click <strong>Save</strong> to activate.</li>
</ol>

<h3>Viewing Webhook Logs</h3>
<p>Click <strong>View Logs</strong> on any integration to see delivery history including timestamps, HTTP status codes, and response bodies. Failed webhooks can be retried with a single click.</p>

<div class="callout callout-info">
    <strong>Tip</strong>
    Use a service like <strong>webhook.site</strong> to test your webhook URL before connecting it to your CRM. This lets you inspect the exact payload format being sent.
</div>


<h2 id="api-tokens">API Tokens</h2>

<p>API tokens allow you to access the platform's REST API programmatically. Use them to build custom integrations, automate workflows, or connect third-party applications.</p>

<h3>Creating an API Token</h3>
<ol class="steps">
    <li>Go to <strong>Settings &rarr; API Keys</strong>.</li>
    <li>Click <strong>Create API Key</strong>.</li>
    <li>Enter a <strong>Name</strong> for the key (e.g., "CRM Integration", "Mobile App").</li>
    <li>The system generates a unique API token. <strong>Copy it immediately</strong> &mdash; it will not be shown again.</li>
    <li>Use the token in your API requests as a Bearer token in the Authorization header.</li>
</ol>

<h3>Managing API Keys</h3>
<ul>
    <li><strong>Enable/Disable</strong> &mdash; Toggle a key on or off without deleting it</li>
    <li><strong>Delete</strong> &mdash; Permanently revoke access for a key</li>
    <li><strong>Rename</strong> &mdash; Update the key's name for better organization</li>
</ul>

<h3>API Documentation</h3>
<p>Interactive API documentation is available at <code>/api/documentation</code>. It lists all available endpoints, request/response formats, and authentication requirements. The API supports managing campaigns, contacts, calls, sequences, AI agents, and more.</p>

<div class="callout callout-warning">
    <strong>Security</strong>
    Treat API tokens like passwords. Never share them in emails, chat messages, or public repositories. If a token is compromised, delete it immediately and create a new one.
</div>


<h2 id="settings">Settings</h2>

<h3>Profile</h3>
<p>Update your name, email, and basic account information at <strong>Settings &rarr; Profile</strong>. You can also delete your account from this page (this action is irreversible).</p>

<h3>Password</h3>
<p>Change your password at <strong>Settings &rarr; Password</strong>. You will need to enter your current password for security.</p>

<h3>Two-Factor Authentication</h3>
<p>Add an extra layer of security to your account at <strong>Settings &rarr; Two-Factor Authentication</strong>. Once enabled, you will need to enter a 6-digit code from your authenticator app (Google Authenticator, Authy, etc.) each time you log in. Recovery codes are provided for emergency access.</p>

<h3>Appearance</h3>
<p>Switch between <strong>Light</strong>, <strong>Dark</strong>, and <strong>System</strong> mode at <strong>Settings &rarr; Appearance</strong>.</p>

<h3>Language</h3>
<p>Change the platform language using the language switcher in the top navigation bar. Available languages: English, Spanish, French, Arabic, Chinese, Hindi, Portuguese, Russian, Japanese, and German.</p>


<!-- ============================================ -->
<!-- ADMINISTRATION -->
<!-- ============================================ -->
<h2 id="admin-users">Admin: User Management</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>Manage all system users from <strong>Users</strong> in the admin menu. You can:</p>
<ul>
    <li><strong>Create</strong> new user accounts with specific roles (Admin, Customer, or Agent)</li>
    <li><strong>Edit</strong> user information and reset passwords</li>
    <li><strong>Activate / Deactivate</strong> accounts (deactivated users cannot log in)</li>
    <li><strong>View</strong> user activity, statistics, and credit balance</li>
    <li><strong>Impersonate</strong> a user to see the platform from their perspective (for troubleshooting)</li>
    <li><strong>Delete</strong> user accounts permanently</li>
</ul>


<h2 id="admin-roles">Admin: Roles & Permissions</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>Configure what each role can do from <strong>Roles</strong> in the admin menu. The system comes with 100+ granular permissions organized by module:</p>
<ul>
    <li><strong>Campaigns</strong> &mdash; View, Create, Edit, Delete, Launch, Pause, Resume, Export</li>
    <li><strong>Contacts</strong> &mdash; View, Add, Edit, Delete, Import, Export, Bulk Operations</li>
    <li><strong>Calls</strong> &mdash; View logs, Initiate, End, View recordings, Download, Export</li>
    <li><strong>SMS</strong> &mdash; View, Send, Templates, Conversations</li>
    <li><strong>AI Agents</strong> &mdash; View, Create, Edit, Delete, Monitor Live, View Calls</li>
    <li><strong>Analytics</strong> &mdash; View reports, Export, Hot Leads, Smart Scheduling</li>
    <li><strong>Audio Files</strong> &mdash; Upload, Delete, Use in campaigns</li>
    <li><strong>Settings</strong> &mdash; Profile, Twilio, API keys, KYC</li>
    <li><strong>Admin</strong> &mdash; Users, Pricing, Credits, System Config, Theme, Languages, Logs</li>
</ul>


<h2 id="admin-kyc">Admin: KYC Review</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>Review and approve user verification submissions from <strong>Admin &rarr; KYC Management</strong>.</p>
<ol class="steps">
    <li>View the pending submissions queue with counts for Basic and Business tiers.</li>
    <li>Click on a submission to review the user's documents and information.</li>
    <li>For Business tier: Download and review uploaded documents (ID, Business Registration, Selfie with ID).</li>
    <li>Click <strong>Approve</strong> with optional notes, or <strong>Reject</strong> with a required reason (which is sent to the user via notification).</li>
</ol>

<h3>KYC Settings</h3>
<p>Configure which KYC tiers are required and what documents must be submitted. You can also directly approve users without requiring them to go through the KYC flow.</p>


<h2 id="admin-numbers">Admin: Phone Number Inventory</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<h3>Syncing Numbers from Twilio</h3>
<ol class="steps">
    <li>Go to <strong>Admin &rarr; Phone Numbers</strong>.</li>
    <li>Click <strong>Sync from Twilio</strong>.</li>
    <li>Select a <strong>Country</strong> and optionally an <strong>Area Code</strong>.</li>
    <li>Click <strong>Sync</strong>. Up to 50 available numbers will be imported into your inventory.</li>
</ol>

<h3>Assigning a Number to a User</h3>
<ol class="steps">
    <li>Find the number in the inventory and click <strong>Assign</strong>.</li>
    <li>Select the user from the dropdown.</li>
    <li>Review the monthly cost and user's credit balance.</li>
    <li>Optionally check <strong>Skip credit deduction</strong> to assign for free (e.g., for promotions).</li>
    <li>Click <strong>Confirm Assignment</strong>. The monthly fee will be charged from the user's credits on the 1st of each month.</li>
</ol>

<h3>Handling Number Requests</h3>
<p>When users request numbers, they appear in <strong>Admin &rarr; Number Requests</strong>. Review each request, check the pricing and user's balance, then Approve or Reject with a reason.</p>


<h2 id="admin-sip">Admin: SIP Trunking & BYOC</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>SIP Trunking and BYOC (Bring Your Own Carrier) allow you to use your existing telephony provider instead of Twilio's standard phone numbers. This can reduce costs and give you more control over your voice infrastructure.</p>

<h3>SIP Trunking</h3>
<p>Connect your platform to SIP-based telephony services through Twilio's SIP Trunking:</p>
<ol class="steps">
    <li>Go to <strong>Twilio Integration &rarr; SIP Trunk</strong>.</li>
    <li>Click <strong>Setup Wizard</strong> to begin the guided configuration.</li>
    <li>The wizard will create and configure the SIP trunk on your Twilio account.</li>
    <li>Assign phone numbers to the trunk.</li>
    <li>Use the <strong>Health Check</strong> to verify connectivity.</li>
</ol>

<h3>BYOC (Bring Your Own Carrier)</h3>
<p>Connect a third-party SIP provider to route calls through your existing carrier:</p>
<ol class="steps">
    <li>Go to <strong>Twilio Integration &rarr; BYOC</strong>.</li>
    <li>Click <strong>Setup Wizard</strong> to configure the BYOC trunk.</li>
    <li>Add your SIP provider details (SIP URI, authentication credentials).</li>
    <li>Configure voice webhooks for call routing.</li>
    <li>Test the connection to verify calls route correctly.</li>
</ol>

<div class="callout callout-info">
    <strong>When to Use SIP Trunking / BYOC</strong>
    These features are for advanced users who have existing telephony infrastructure or need to reduce per-minute costs by using their own carrier agreements. Most users should use the standard phone number management instead.
</div>


<h2 id="admin-pricing">Admin: Pricing Rules</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>Set how much customers pay for calls, SMS, and phone numbers from <strong>Admin &rarr; Pricing</strong>. The pricing system adds your markup on top of the base Twilio cost.</p>

<h3>Markup Types</h3>
<table>
    <tr><th>Type</th><th>How It Works</th><th>Example</th></tr>
    <tr><td>Percentage</td><td>Add % on top of base cost</td><td>Base $0.01 + 50% = Customer pays $0.015</td></tr>
    <tr><td>Fixed</td><td>Add flat amount per unit</td><td>Base $0.01 + $0.005 = Customer pays $0.015</td></tr>
    <tr><td>Hybrid</td><td>Both percentage + fixed</td><td>Base $0.01 + 20% + $0.002 = Customer pays $0.014</td></tr>
</table>

<p>You can set rules per country and service type (Voice, SMS, Phone Numbers). Use <strong>Bulk Update</strong> to change the markup across all countries at once, or <strong>Bulk Import</strong> to upload pricing from a CSV file.</p>

<h3>Pricing Calculator</h3>
<p>Use the <strong>Calculator</strong> tab to preview what a customer will be charged for any call route. It shows the base cost, your markup, the final customer price, and your profit per minute/message.</p>


<h2 id="admin-credits">Admin: Credit Management</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>Monitor and manage all user credit balances from <strong>Admin &rarr; Credit Management</strong>.</p>
<ul>
    <li><strong>Overview</strong> &mdash; Total credits in the system, low-balance users, recent transaction count, revenue summary</li>
    <li><strong>User Credits</strong> &mdash; Click any user to manually add or deduct credits with a required reason (full audit trail maintained)</li>
    <li><strong>Transactions</strong> &mdash; Full ledger of all credits and debits across all users with filtering and search</li>
    <li><strong>Reports</strong> &mdash; Monthly activity charts showing credits purchased vs. spent. Export reports as CSV.</li>
</ul>


<h2 id="admin-profit">Admin: Profit Dashboard</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>View your business financials at a glance from <strong>Admin &rarr; Profit Dashboard</strong>:</p>
<ul>
    <li><strong>Total Revenue</strong> &mdash; All customer credit top-ups</li>
    <li><strong>Total Cost</strong> &mdash; Actual Twilio and third-party service expenses</li>
    <li><strong>Total Profit</strong> &mdash; Revenue minus costs</li>
    <li><strong>Profit Margin</strong> &mdash; Percentage of revenue retained as profit</li>
</ul>

<p>The <strong>Profit Analytics</strong> page provides deeper breakdowns by service type (voice, SMS, numbers), by customer, and by time period. Export all data as CSV for accounting purposes.</p>


<h2 id="admin-system">Admin: System Configuration</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>System Configuration is password-protected for additional security. You will be asked to re-enter your password before accessing these settings.</p>

<h3>Application Settings</h3>
<p>Configure the application name, URL, timezone, default language, logo, favicon, and environment mode. Upload a custom logo and favicon to brand the platform.</p>

<div class="callout callout-danger">
    <strong>Warning</strong>
    Always keep <strong>Debug Mode</strong> disabled in production. Debug mode can expose sensitive information including database credentials and API keys.
</div>

<h3>Payment Gateways</h3>
<p>Configure one or more payment gateways for customer credit top-ups. Each gateway can be independently enabled/disabled and supports test mode:</p>
<table>
    <tr><th>Gateway</th><th>Required Keys</th><th>Notes</th></tr>
    <tr><td><strong>Stripe</strong></td><td>Publishable Key, Secret Key, Webhook Secret</td><td>Supports 20 currencies, test mode available</td></tr>
    <tr><td><strong>Razorpay</strong></td><td>API Key, Secret Key, Webhook Secret</td><td>Popular in India, supports INR and 19 other currencies</td></tr>
    <tr><td><strong>Paystack</strong></td><td>Public Key, Secret Key, Webhook Secret</td><td>Popular in Africa, supports NGN, GHS, ZAR, KES, USD</td></tr>
    <tr><td><strong>PayPal</strong></td><td>Client ID, Client Secret, Webhook ID</td><td>Supports 25 currencies, sandbox mode available</td></tr>
</table>

<h3>Email Configuration</h3>
<p>Set up email delivery for notifications, password resets, and KYC updates. Supports SMTP, Mailgun, and Amazon SES. Configure the sender name, sender email, SMTP host, port, and credentials. Use the <strong>Send Test Email</strong> button to verify your configuration works.</p>


<h2 id="admin-theme">Admin: Theme & Branding</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>Customize the look and feel of your platform's public-facing landing page from <strong>Admin &rarr; Theme</strong>:</p>
<ul>
    <li><strong>Logo & Favicon</strong> &mdash; Upload your company logo and browser favicon</li>
    <li><strong>Hero Section</strong> &mdash; Main headline, subtitle, and call-to-action button</li>
    <li><strong>Features Section</strong> &mdash; Highlight key platform features with icons and descriptions</li>
    <li><strong>Benefits Section</strong> &mdash; List the advantages of using your service</li>
    <li><strong>Use Cases Section</strong> &mdash; Show example applications for different industries</li>
    <li><strong>Statistics Section</strong> &mdash; Display impressive numbers (users, calls made, etc.)</li>
    <li><strong>Pricing Section</strong> &mdash; Show pricing tiers for potential customers</li>
    <li><strong>FAQ Section</strong> &mdash; Common questions and answers</li>
    <li><strong>Footer</strong> &mdash; Customize footer links, social media URLs, and copyright text</li>
    <li><strong>SEO Settings</strong> &mdash; Meta title, description, and keywords for search engine optimization</li>
</ul>


<h2 id="admin-languages">Admin: Language Management</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<p>Manage the 10 supported interface languages from <strong>Admin &rarr; Languages</strong>:</p>
<ul>
    <li><strong>Enable / Disable</strong> specific languages (disabled languages won't appear in the language switcher)</li>
    <li><strong>Set Default</strong> language for new users</li>
    <li><strong>Edit Translations</strong> &mdash; Click on any language to view and edit individual translation strings</li>
    <li><strong>Translation Progress</strong> &mdash; See the completion percentage for each language</li>
    <li><strong>Export / Import</strong> &mdash; Download translations as JSON for external editing, or import updated translations</li>
</ul>

<p>Supported languages: English, Spanish, French, Arabic (RTL), Chinese (Simplified), Hindi, Portuguese, Russian, Japanese, and German.</p>


<h2 id="admin-logs">Admin: Logs & Monitoring</h2>
<p><span class="badge badge-admin">Admin Only</span></p>

<h3>Call Logs</h3>
<p>View all calls across the entire system with advanced filters including user, campaign, status, direction, date range, and sentiment score. The logs include AI-powered analysis showing sentiment (positive/neutral/negative) and lead quality scores for each call.</p>

<h3>Error Logs</h3>
<p>Monitor Twilio errors and system issues in real time. Shows error codes, log levels (ERROR, WARNING, INFO), timestamps, request/response details, and resource identifiers. Click <strong>Refresh</strong> to fetch the latest errors from Twilio's debugger.</p>

<h3>Cron Monitor</h3>
<p>Track background job health including queue workers, scheduled tasks, and sync operations. View execution history, success/failure rates, last run timestamps, and average processing times. This helps you ensure all background processes are running correctly.</p>


<!-- ============================================ -->
<!-- HELP -->
<!-- ============================================ -->
<h2 id="faq">Frequently Asked Questions</h2>

<h4>How are credits deducted?</h4>
<p>Credits are deducted based on actual usage: per-minute for voice calls, per-segment (160 characters) for SMS, and monthly for phone numbers. The exact rates depend on the destination country and pricing rules set by your admin.</p>

<h4>Can I use my own Twilio account?</h4>
<p>If you are an admin, yes. Enter your Twilio credentials in System Configuration. For multi-tenant setups, the admin's Twilio account is shared across all users, with each user getting their own assigned phone numbers.</p>

<h4>How many contacts can I import at once?</h4>
<p>The CSV import handles large files efficiently, processing in the background. For best results, keep imports under 10,000 contacts per file. Larger datasets can be split across multiple files.</p>

<h4>What happens when a campaign runs out of credits?</h4>
<p>The campaign will pause automatically when your credit balance is insufficient to continue making calls. Top up your credits and the campaign can be resumed from where it left off.</p>

<h4>Can AI agents handle multiple calls at the same time?</h4>
<p>Yes. Each AI agent can handle multiple simultaneous calls. The system creates a separate conversation context for each call, so conversations don't mix.</p>

<h4>What languages can the AI agent speak?</h4>
<p>AI agents use Amazon Polly Generative and Google Chirp3-HD voices for speech output. These voices are primarily English. The AI language model can understand and respond in multiple languages, but the spoken voice output quality is best in English.</p>

<h4>How do I get call recordings?</h4>
<p>Enable recording when creating a campaign (in the settings section) or when creating an AI agent (in call settings). Recordings appear in the Call History and can be played directly in the browser or downloaded.</p>

<h4>Can I integrate with my CRM?</h4>
<p>Yes. Use the Integrations feature to set up webhook-based connections to any CRM or tool that accepts HTTP POST requests (HubSpot, Salesforce, Zoho, etc.). For more advanced integrations, use the REST API with API tokens.</p>

<h4>What payment methods are supported?</h4>
<p>The platform supports 4 payment gateways: Stripe (credit/debit cards worldwide), Razorpay (popular in India), Paystack (popular in Africa), and PayPal. Your admin determines which gateways are available.</p>

<h4>How do I switch the platform language?</h4>
<p>Click the language dropdown in the top navigation bar and select your preferred language. The change takes effect immediately and is saved to your profile.</p>

<h4>What is KYC and why is it required?</h4>
<p>KYC (Know Your Customer) is a verification process that helps prevent fraud and comply with telecommunications regulations. Basic verification (phone number) unlocks core features, while Business verification (documents) unlocks advanced AI features.</p>


<h2 id="troubleshooting">Troubleshooting</h2>

<table>
    <tr><th>Problem</th><th>Possible Cause</th><th>Solution</th></tr>
    <tr><td>Calls not going through</td><td>Twilio credentials not configured or invalid</td><td>Check <strong>Settings &rarr; Twilio</strong> and verify your Account SID and Auth Token. Ensure the phone number is active in Twilio.</td></tr>
    <tr><td>Campaign stuck in "Running" with no calls</td><td>Queue workers not running, insufficient credits, or no phone number assigned</td><td>Verify background processes are running (ask your server admin). Top up credits and ensure a phone number is selected for the campaign.</td></tr>
    <tr><td>"No phone numbers available"</td><td>No numbers have been assigned to your account</td><td>Request a number from <strong>Numbers &rarr; Available Numbers</strong> or ask your admin to assign one.</td></tr>
    <tr><td>AI agent not responding</td><td>OpenRouter API key not configured or invalid</td><td>(Admin) Verify the API key in server environment configuration. Use <strong>Test Configuration</strong> on the agent page to diagnose.</td></tr>
    <tr><td>AI agent voice sounds wrong</td><td>Selected voice unavailable or misconfigured</td><td>Edit the agent and try a different voice. Amazon Polly voices (Joanna, Matthew) tend to be most reliable.</td></tr>
    <tr><td>SMS not sending</td><td>Phone number doesn't have SMS capability</td><td>Check the number's capabilities in <strong>Numbers &rarr; My Numbers</strong>. SMS requires an SMS-capable number.</td></tr>
    <tr><td>Payment fails during top-up</td><td>Payment gateway not configured</td><td>(Admin) Go to <strong>System Configuration</strong> and set up at least one payment gateway with valid credentials. Use test mode first.</td></tr>
    <tr><td>Can't access certain features</td><td>KYC not completed or role permissions restricted</td><td>Complete KYC verification in <strong>Settings &rarr; KYC</strong>. For permission issues, ask your admin to check your role.</td></tr>
    <tr><td>KYC submission rejected</td><td>Documents were unclear or didn't match</td><td>Check the rejection reason on your KYC page and resubmit with clearer documents.</td></tr>
    <tr><td>Webhook integration failing</td><td>Incorrect URL or authentication</td><td>Use the <strong>Test</strong> button on the integration to check connectivity. Verify the URL and credentials with your CRM provider.</td></tr>
    <tr><td>Contacts not importing</td><td>CSV format incorrect or missing required fields</td><td>Download the sample CSV template and ensure your file matches the column format. Phone numbers must be in international format (+1...).</td></tr>
    <tr><td>Platform is slow</td><td>Large dataset or server resources</td><td>Contact your administrator. Server-side optimizations like Redis caching and additional queue workers can improve performance.</td></tr>
    <tr><td>Real-time updates not working</td><td>WebSocket server (Reverb) not running</td><td>(Admin) Ensure the Reverb WebSocket server is running. Check server process manager (PM2, Supervisor) status.</td></tr>
</table>


<h2 id="tips">Tips & Best Practices</h2>

<h3>Campaigns</h3>
<ul>
    <li>Start with a small test campaign (10-20 contacts) before scaling up to hundreds or thousands</li>
    <li>Use <strong>On-Demand Call</strong> to test your message on a single contact first</li>
    <li>Set reasonable <strong>Concurrent Call Limits</strong> (5-10) to avoid overwhelming your phone line</li>
    <li>Enable <strong>Recording</strong> for quality assurance and compliance purposes</li>
    <li>Use message <strong>Variables</strong> like <code>@{{first_name}}</code> to personalize calls and increase engagement</li>
    <li>Save successful campaigns as <strong>Templates</strong> for faster setup next time</li>
    <li>Use <strong>Smart Scheduling</strong> data to launch campaigns at optimal times for higher answer rates</li>
</ul>

<h3>AI Agents</h3>
<ul>
    <li>Write clear, specific <strong>System Prompts</strong> that tell the AI exactly how to behave, what to say, and what not to say</li>
    <li>Keep the <strong>Knowledge Base</strong> focused and concise &mdash; shorter, well-structured content works better than long documents</li>
    <li>Use <strong>Polaris Alpha</strong> model for the fastest response times in phone conversations</li>
    <li>Set up <strong>Transfer Keywords</strong> so callers can always reach a human when needed</li>
    <li>Monitor the <strong>Live Calls</strong> page initially to make sure the AI is responding correctly</li>
    <li>Review call <strong>Transcripts</strong> regularly to identify areas where the AI can improve</li>
    <li>Set a reasonable <strong>Max Duration</strong> (e.g., 10-15 minutes) to prevent runaway costs</li>
</ul>

<h3>Contacts & Data</h3>
<ul>
    <li>Clean your contact data before importing &mdash; ensure phone numbers are in international format (+1...)</li>
    <li>Use <strong>Tags</strong> to segment contacts for targeted campaigns (e.g., "interested", "callback", "VIP")</li>
    <li>Organize contacts into <strong>Lists</strong> by purpose (leads, customers, events, regions)</li>
    <li>Use the <strong>AI Quality Check</strong> tool to find formatting issues and duplicates before launching campaigns</li>
    <li>Regularly <strong>Export</strong> your contacts as a backup</li>
</ul>

<h3>Credits & Cost Management</h3>
<ul>
    <li>Monitor your credit balance before launching large campaigns</li>
    <li>Use the <strong>Pricing Calculator</strong> to estimate costs before committing</li>
    <li>Review your <strong>Transaction History</strong> regularly to understand spending patterns</li>
    <li>For AI agents, use <strong>free models</strong> (Polaris Alpha, GPT OSS 20B) to minimize costs</li>
</ul>

<h3>Security</h3>
<ul>
    <li>Enable <strong>Two-Factor Authentication</strong> for all admin and high-privilege accounts</li>
    <li>Use strong, unique passwords for every account</li>
    <li>Complete <strong>KYC Verification</strong> as soon as possible to unlock full features</li>
    <li>Never share API tokens or credentials via email, chat, or public channels</li>
    <li>Rotate API tokens periodically, especially if team members leave</li>
</ul>


<h2 id="support">Support & Help</h2>

<p>If you need assistance that this documentation doesn't cover:</p>

<ul>
    <li><strong>Contact your system administrator</strong> for account-related issues, permissions, and billing questions</li>
    <li><strong>Check the Error Logs</strong> (Admin) for technical issues related to Twilio or system errors</li>
    <li><strong>Use the Test Configuration tool</strong> for AI agents to diagnose connection problems</li>
    <li><strong>Review call transcripts</strong> to understand AI agent behavior and improve prompts</li>
    <li><strong>Check the API documentation</strong> at <code>/api/documentation</code> for integration help</li>
</ul>

<div class="callout callout-success">
    <strong>Quick Reference</strong>
    <ul style="margin-bottom: 0;">
        <li><strong>Dashboard</strong> &mdash; Overview of your account activity</li>
        <li><strong>Campaigns</strong> &mdash; Create and manage automated calling campaigns</li>
        <li><strong>Contacts</strong> &mdash; Manage your contact database with lists and tags</li>
        <li><strong>AI Agents</strong> &mdash; Deploy intelligent voice agents for autonomous calls</li>
        <li><strong>SMS</strong> &mdash; Send and receive text messages</li>
        <li><strong>Analytics</strong> &mdash; View performance metrics, hot leads, and scheduling insights</li>
        <li><strong>Credits</strong> &mdash; Manage billing and payments</li>
        <li><strong>Integrations</strong> &mdash; Connect to external CRMs and tools</li>
        <li><strong>Settings</strong> &mdash; Account preferences, security, and API keys</li>
    </ul>
</div>

<p style="margin-top: 48px; color: var(--text-muted); font-size: 13px; text-align: center;">
    {{ config('app.name', 'Teleman') }} Documentation &mdash; Last updated {{ now()->format('F Y') }}
</p>

</main>

</body>
</html>
