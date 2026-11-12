<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_sip_trunks', function (Blueprint $table) {
            // Routing mode flag
            $table->boolean('routes_to_local_pbx')->default(false)->after('trunk_domain_name');

            // Local PBX SIP Configuration
            $table->string('local_pbx_sip_uri', 255)->nullable()->after('routes_to_local_pbx');
            // e.g., "sip:192.168.1.100:5060" or "sip:pbx.company.com"

            $table->string('local_pbx_ip', 45)->nullable()->after('local_pbx_sip_uri'); // IPv4 or IPv6
            $table->integer('local_pbx_port')->default(5060)->nullable()->after('local_pbx_ip');
            $table->enum('local_pbx_transport', ['udp', 'tcp', 'tls'])->default('udp')->nullable()->after('local_pbx_port');

            // Optional authentication for secure PBX
            $table->string('local_pbx_username', 100)->nullable()->after('local_pbx_transport');
            $table->text('local_pbx_password')->nullable()->after('local_pbx_username'); // Encrypted

            // Twilio Termination URI (what we create in Twilio pointing to user's PBX)
            $table->string('twilio_termination_uri', 255)->nullable()->after('local_pbx_password');
            $table->string('twilio_termination_sid', 34)->nullable()->after('twilio_termination_uri');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_sip_trunks', function (Blueprint $table) {
            $table->dropColumn([
                'routes_to_local_pbx',
                'local_pbx_sip_uri',
                'local_pbx_ip',
                'local_pbx_port',
                'local_pbx_transport',
                'local_pbx_username',
                'local_pbx_password',
                'twilio_termination_uri',
                'twilio_termination_sid',
            ]);
        });
    }
};
