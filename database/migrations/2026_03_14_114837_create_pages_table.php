<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed default legal pages
        DB::table('pages')->insert([
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'content' => '<h2>Privacy Policy</h2><p>This privacy policy describes how we collect, use, and protect your personal information when you use our services.</p><h3>Information We Collect</h3><p>We collect information you provide directly to us, such as when you create an account, make a purchase, or contact us for support.</p><h3>How We Use Your Information</h3><p>We use the information we collect to provide, maintain, and improve our services, process transactions, and communicate with you.</p><h3>Data Security</h3><p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p><h3>Contact Us</h3><p>If you have any questions about this privacy policy, please contact us.</p>',
                'meta_title' => 'Privacy Policy',
                'meta_description' => 'Learn how we collect, use, and protect your personal information.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'terms-and-conditions',
                'title' => 'Terms and Conditions',
                'content' => '<h2>Terms and Conditions</h2><p>Please read these terms and conditions carefully before using our services.</p><h3>Acceptance of Terms</h3><p>By accessing or using our services, you agree to be bound by these terms and conditions.</p><h3>Use of Services</h3><p>You agree to use our services only for lawful purposes and in accordance with these terms.</p><h3>Account Responsibility</h3><p>You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p><h3>Limitation of Liability</h3><p>Our services are provided on an "as is" basis. We make no warranties, expressed or implied, regarding the reliability or availability of our services.</p><h3>Changes to Terms</h3><p>We reserve the right to modify these terms at any time. Continued use of our services after changes constitutes acceptance of the new terms.</p>',
                'meta_title' => 'Terms and Conditions',
                'meta_description' => 'Read our terms and conditions for using our services.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
