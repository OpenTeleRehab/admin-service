<p>Good day!</p>
<p>You have created a new OpenTeleRehab organization for {{ $org_name }}. Creation status is {{ $status }}.</p>

@if ($status === App\Models\Organization::ONGOING_ORG_STATUS)
    <p>We will inform you when the organization creation is successful.</p>

    @if (!$internal)
        <p>Please wait for an account setup email confirmation from the system. Once received please complete the setup and reset your password.</p>
    @endif
@endif

@if ($status === App\Models\Organization::PENDING_ORG_STATUS)
    <p>Organization creation is pending. A system admin needs to setup the following:</p>
    <ul>
        <li>Rocket Chat Key</li>
        <li>Keycloak Realm Keys</li>
    </ul>
@endif

@if ($status === App\Models\Organization::FAILED_ORG_STATUS)
    <p>Organization creation has failed.</p>
@endif

@if ($status === App\Models\Organization::SUCCESS_ORG_STATUS)
    <p>Organization creation is successful. You can visit Organization Admin Portal to complete the setup of your organization.</p>
@endif

<p>Have a nice day!</p>
