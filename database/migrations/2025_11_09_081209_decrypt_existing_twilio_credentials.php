<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Decrypt existing TwilioCredential records
        $credentials = DB::table('twilio_credentials')->get();
        
        foreach ($credentials as $credential) {
            if ($credential->auth_token) {
                try {
                    // Try to decrypt the token
                    $decrypted = Crypt::decryptString($credential->auth_token);
                    
                    // Update with decrypted value
                    DB::table('twilio_credentials')
                        ->where('id', $credential->id)
                        ->update(['auth_token' => $decrypted]);
                        
                    echo "✓ Decrypted credential ID: {$credential->id}\n";
                } catch (\Exception $e) {
                    // If decryption fails, assume it's already plain text or corrupted
                    echo "⚠ Skipped credential ID: {$credential->id} - {$e->getMessage()}\n";
                }
            }
        }
        
        // Decrypt existing TwilioGlobalConfig records
        $configs = DB::table('twilio_global_config')->get();
        
        foreach ($configs as $config) {
            if ($config->auth_token) {
                try {
                    // Try to decrypt the token
                    $decrypted = decrypt($config->auth_token);
                    
                    // Update with decrypted value
                    DB::table('twilio_global_config')
                        ->where('id', $config->id)
                        ->update(['auth_token' => $decrypted]);
                        
                    echo "✓ Decrypted global config ID: {$config->id}\n";
                } catch (\Exception $e) {
                    // If decryption fails, assume it's already plain text or corrupted
                    echo "⚠ Skipped global config ID: {$config->id} - {$e->getMessage()}\n";
                }
            }
            
            if ($config->api_key_secret) {
                try {
                    $decrypted = decrypt($config->api_key_secret);
                    DB::table('twilio_global_config')
                        ->where('id', $config->id)
                        ->update(['api_key_secret' => $decrypted]);
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse - we don't want to re-encrypt
    }
};
