<?php
/** New-user flashcard guide — opened from “Take a quick tour” on the landing page. */
?>
<div id="getStartedGuideModal" class="guide-modal" hidden aria-hidden="true" role="dialog" aria-labelledby="guideModalTitle" aria-modal="true">
    <div class="guide-modal__backdrop" data-guide-close aria-hidden="true"></div>
    <div class="guide-modal__panel">
        <header class="guide-modal__head">
            <div class="guide-modal__brand">
                <span class="guide-modal__logo" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                <span class="guide-modal__wordmark">EVENTIFY</span>
            </div>
            <button type="button" class="guide-modal__close" data-guide-close aria-label="Close guide">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>

        <div class="guide-flashcard-viewport">
            <div class="guide-flashcard-stage" id="guideFlashcardStage">
                <article class="guide-flashcard is-active" data-guide-slide="0">
                    <div class="guide-flashcard__icon guide-flashcard__icon--welcome" aria-hidden="true">
                        <i class="fas fa-school"></i>
                    </div>
                    <p class="guide-flashcard__step">Step 1 of 5</p>
                    <h2 class="guide-flashcard__title" id="guideModalTitle">Welcome to EVENTIFY</h2>
                    <p class="guide-flashcard__text">
                        EVENTIFY is Western Leyte College&rsquo;s hub for school events — one place to plan activities,
                        RSVP, check in with QR, and share photos after the event.
                    </p>
                    <ul class="guide-flashcard__bullets">
                        <li><i class="fas fa-check" aria-hidden="true"></i> See upcoming and past events on a calendar</li>
                        <li><i class="fas fa-check" aria-hidden="true"></i> Filter by your department</li>
                        <li><i class="fas fa-check" aria-hidden="true"></i> Get bell notifications when things change</li>
                    </ul>
                </article>

                <article class="guide-flashcard" data-guide-slide="1" hidden aria-hidden="true">
                    <div class="guide-flashcard__icon guide-flashcard__icon--student" aria-hidden="true">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <p class="guide-flashcard__step">Step 2 of 5</p>
                    <h2 class="guide-flashcard__title">If you&rsquo;re a student</h2>
                    <p class="guide-flashcard__text">
                        Register with your school email, then use your dashboard to follow events for your department.
                    </p>
                    <ul class="guide-flashcard__bullets">
                        <li><i class="fas fa-calendar-alt" aria-hidden="true"></i> Browse events and RSVP from your calendar</li>
                        <li><i class="fas fa-qrcode" aria-hidden="true"></i> Scan QR codes to check in on event day</li>
                        <li><i class="fas fa-th-large" aria-hidden="true"></i> Open the <strong>Activities hub</strong> for day schedules</li>
                        <li><i class="fas fa-images" aria-hidden="true"></i> View published photo galleries after events</li>
                    </ul>
                </article>

                <article class="guide-flashcard" data-guide-slide="2" hidden aria-hidden="true">
                    <div class="guide-flashcard__icon guide-flashcard__icon--organizer" aria-hidden="true">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <p class="guide-flashcard__step">Step 3 of 5</p>
                    <h2 class="guide-flashcard__title">If you&rsquo;re an organizer</h2>
                    <p class="guide-flashcard__text">
                        Organizers create events and day activities. New events go to admin for review before students can see them.
                    </p>
                    <ul class="guide-flashcard__bullets">
                        <li><i class="fas fa-plus-circle" aria-hidden="true"></i> Create events with date, venue, and department</li>
                        <li><i class="fas fa-envelope-open-text" aria-hidden="true"></i> Enter the approval code sent to your email</li>
                        <li><i class="fas fa-list-ul" aria-hidden="true"></i> Add activities (sessions) inside each event</li>
                        <li><i class="fas fa-chart-line" aria-hidden="true"></i> Track RSVP and attendance from your dashboard</li>
                    </ul>
                </article>

                <article class="guide-flashcard" data-guide-slide="3" hidden aria-hidden="true">
                    <div class="guide-flashcard__icon guide-flashcard__icon--staff" aria-hidden="true">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <p class="guide-flashcard__step">Step 4 of 5</p>
                    <h2 class="guide-flashcard__title">Admin &amp; multimedia teams</h2>
                    <p class="guide-flashcard__text">
                        Staff accounts keep events accurate and visual content ready for students.
                    </p>
                    <ul class="guide-flashcard__bullets">
                        <li><i class="fas fa-clipboard-check" aria-hidden="true"></i> <strong>Admins</strong> review pending events and send approval codes</li>
                        <li><i class="fas fa-camera" aria-hidden="true"></i> <strong>Multimedia</strong> uploads event and activity photos</li>
                        <li><i class="fas fa-user-shield" aria-hidden="true"></i> Photo moderators approve uploads before they go live</li>
                    </ul>
                </article>

                <article class="guide-flashcard" data-guide-slide="4" hidden aria-hidden="true">
                    <div class="guide-flashcard__icon guide-flashcard__icon--ready" aria-hidden="true">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <p class="guide-flashcard__step">Step 5 of 5</p>
                    <h2 class="guide-flashcard__title">You&rsquo;re ready to begin</h2>
                    <p class="guide-flashcard__text">
                        New students and staff can register in a few minutes. Already have an account? Sign in and go straight to your dashboard.
                    </p>
                    <div class="guide-flashcard__cta">
                        <button type="button" class="btn btn-shimmer guide-flashcard__cta-primary" id="guideRegisterBtn">
                            Create an account
                        </button>
                        <button type="button" class="btn btn-outline guide-flashcard__cta-secondary" id="guideLoginBtn">
                            I already have an account
                        </button>
                    </div>
                </article>
            </div><!-- .guide-flashcard-stage -->
        </div><!-- .guide-flashcard-viewport -->

        <footer class="guide-modal__foot">
            <p class="guide-swipe-hint"><i class="fas fa-hand-pointer" aria-hidden="true"></i> Swipe left or right to navigate</p>
            <div class="guide-dots" id="guideDots" role="tablist" aria-label="Guide steps"></div>
            <div class="guide-modal__nav" id="guideModalNav" aria-hidden="false">
                <button type="button" class="guide-nav-btn guide-nav-btn--ghost" id="guidePrevBtn" disabled>
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back
                </button>
                <button type="button" class="guide-nav-btn guide-nav-btn--primary" id="guideNextBtn">
                    Next <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
            <button type="button" class="guide-skip-btn" id="guideSkipBtn">Skip tour</button>
        </footer>
    </div>
</div>
