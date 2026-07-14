<?php
/**
 * Shared Terms body (standalone page + landing modal).
 * @var string $legal_terms_context 'standalone' | 'modal'
 */
$legal_terms_context = $legal_terms_context ?? 'standalone';
$base = defined('BASE_URL') ? BASE_URL : '';
?>
<p class="legal-doc-meta"><strong>System:</strong> EVENTIFY - School Events Monitoring System</p>
<p class="legal-doc-meta"><strong>Effectivity:</strong> <?= htmlspecialchars(date('F d, Y')) ?></p>
<p>
    These Terms and Conditions govern the use of EVENTIFY. By creating an account, logging in, or continuing
    to use the system, you agree to these Terms.
</p>

<h3 class="legal-doc-h3">Scope and Purpose</h3>
<p>
    EVENTIFY is an academic school system intended for planning, approving, managing, and monitoring school events,
    attendance, and related multimedia documentation. The system is for authorized users only.
</p>
<p>
    These Terms apply to all roles that access EVENTIFY, including students, organizers, multimedia personnel,
    administrators, and other authorized school users.
</p>

<h3 class="legal-doc-h3">User Responsibilities and Acceptable Use</h3>
<ul class="legal-doc-list">
    <li>Provide accurate and updated account/profile information.</li>
    <li>Use your account credentials responsibly and keep them confidential.</li>
    <li>Use the system only for legitimate school-related event activities.</li>
    <li>Respect role-based permissions and approval processes.</li>
    <li>Upload only lawful, school-appropriate, and authorized content.</li>
</ul>
<p>
    Users are accountable for all activities performed under their accounts unless a compromise is promptly
    reported through official school channels.
</p>

<h3 class="legal-doc-h3">Prohibited Activities</h3>
<ul class="legal-doc-list">
    <li>Unauthorized access, privilege escalation, or attempts to bypass security controls.</li>
    <li>Sharing accounts, impersonation, or use of another user’s credentials.</li>
    <li>Uploading malicious files, offensive material, or content that violates rights of others.</li>
    <li>Tampering with logs, attendance records, event data, or system configurations without authority.</li>
    <li>Using the system for non-school commercial activity or unlawful purposes.</li>
</ul>
<p>
    Violations may result in account suspension, restriction, referral for school disciplinary action,
    and/or legal reporting when required by law.
</p>

<h3 class="legal-doc-h3">Data Ownership and Handling</h3>
<p>
    User data entered into EVENTIFY is processed for school event operations and remains subject to school data governance.
    Users are responsible for ensuring that data they submit is accurate, relevant, and lawfully shared.
</p>
<p>
    System administrators may review, moderate, or remove content when needed for policy enforcement, security, or legal compliance.
</p>
<p>
    Event photos, attendance entries, and operational logs are managed under institutional control for legitimate
    school documentation, reporting, and safety purposes.
</p>

<h3 class="legal-doc-h3">Limitation of Liability</h3>
<p>
    EVENTIFY is an academic system project and is provided on an "as available" basis for school use. While reasonable effort
    is made to maintain reliability and security, the project team and school are not liable for indirect, incidental, or
    consequential damages arising from temporary outages, user misuse, or force majeure conditions.
</p>
<p>
    The project team and school also do not guarantee uninterrupted availability in all environments,
    especially during maintenance, updates, or infrastructure limitations.
</p>

<h3 class="legal-doc-h3">Data Privacy Compliance</h3>
<p>
    EVENTIFY processes personal data in accordance with the Data Privacy Act of 2012 (RA 10173), its IRR, and related NPC issuances.
    <?php if ($legal_terms_context === 'standalone'): ?>
        Please read the <a href="<?= htmlspecialchars($base) ?>/privacy-notice.php">Data Privacy Notice</a> for full details on processing, retention,
        and data subject rights.
    <?php else: ?>
        Please read the <strong>Data Privacy Notice</strong> using the link in the registration form above.
    <?php endif; ?>
</p>
<p>
    Users must process and share personal data in EVENTIFY only for authorized school-related purposes and in
    a manner consistent with RA 10173 and school policy.
</p>

<h3 class="legal-doc-h3">Account Restriction, Suspension, and Termination</h3>
<p>
    EVENTIFY administrators may restrict, suspend, or deactivate accounts that violate these Terms, present
    security risks, or fail to meet required account standards (e.g., approval workflow compliance).
</p>
<p>
    Where appropriate, users may request review or correction through official school support channels.
</p>

<h3 class="legal-doc-h3">Changes to Terms and Continued Use</h3>
<p>
    These Terms may be updated to reflect legal, policy, or system changes. Updated terms will be posted
    in EVENTIFY and take effect upon posting unless otherwise stated.
</p>
<p>
    Continued access or use of EVENTIFY signifies acceptance of the current Terms. If you do not agree,
    you must discontinue use of the system.
</p>
