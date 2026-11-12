<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Info(
 *     title="Teleman Telemarketing API",
 *     version="1.0.0",
 *     description="RESTful API for Teleman telemarketing platform. Integrate with CRM, ERP, and other business applications.",
 *     @OA\Contact(
 *         email="support@teleman.com",
 *         name="Teleman Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 *
 * @OA\Server(
 *     url="https://api.teleman.com",
 *     description="Production Server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your API token in the format: Bearer {token}"
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com"),
 *     @OA\Property(property="timezone", type="string", example="America/New_York"),
 *     @OA\Property(property="credit_balance", type="number", example=100.50),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Campaign",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="type", type="string", enum={"tts", "audio"}),
 *     @OA\Property(property="status", type="string", enum={"draft", "scheduled", "running", "paused", "completed", "stopped"}),
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="phone_number_id", type="integer", nullable=true, description="Phone number used for campaign"),
 *     @OA\Property(property="ai_agent_id", type="integer", nullable=true, description="AI agent automatically assigned based on phone number (read-only)"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Contact",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="phone_number", type="string"),
 *     @OA\Property(property="first_name", type="string"),
 *     @OA\Property(property="last_name", type="string"),
 *     @OA\Property(property="email", type="string"),
 *     @OA\Property(property="company", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Call",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="direction", type="string", enum={"inbound", "outbound"}),
 *     @OA\Property(property="from_number", type="string"),
 *     @OA\Property(property="to_number", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="duration_seconds", type="integer"),
 *     @OA\Property(property="recording_url", type="string", nullable=true),
 *     @OA\Property(property="started_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AiAgent",
 *     type="object",
 *     required={"name", "phone_number"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Sales Support Agent"),
 *     @OA\Property(property="phone_number", type="string", example="+15189001255", description="Phone number assigned to this AI agent"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Handles customer inquiries and sales calls"),
 *     @OA\Property(property="type", type="string", enum={"inbound", "outbound"}, example="inbound"),
 *     @OA\Property(property="system_prompt", type="string", description="System instructions for AI agent behavior"),
 *     @OA\Property(property="model", type="string", example="gpt-4"),
 *     @OA\Property(property="voice", type="string", example="alloy"),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="enable_transfer", type="boolean", example=false),
 *     @OA\Property(property="transfer_number", type="string", nullable=true),
 *     @OA\Property(property="enable_recording", type="boolean", example=true),
 *     @OA\Property(property="max_duration", type="integer", example=300, description="Maximum call duration in seconds"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="error", type="object",
 *         @OA\Property(property="message", type="string"),
 *         @OA\Property(property="code", type="integer")
 *     )
 * )
 */
class ApiDocumentation
{
    // This class is only used for OpenAPI annotations
}
