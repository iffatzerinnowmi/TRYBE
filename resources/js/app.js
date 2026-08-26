import './bootstrap';
import './api';

/* Member 4 — Smart Participant Matching & invitations.
   Each module no-ops immediately if its hook element is not on the page. */
import './candidates';           // suggested-candidates panel (dashboard + study page)
import './participant-profile';  // the candidate profile page
import './study-match';          // study page: criteria + "your match" + respond
import './invitations';          // the participant's invitations page
import './apply';                // Apply to Study button (volunteer studies)
import './completion';           // My studies page + "complete study" modal
import './requirements';         // "what this study requires, and what you meet"
import './competitions';         // competition board + saved competitions
import './feed';                 // recommendation feed + skill-gap coach
/* Member 4 — Referral system. */
import './referrals';            // Refer a Friend (participant + researcher)
import './referral-banner';      // "you were invited by X" on the signup page

/* Member 3 — Karma Credits. */
import './karma';
/* Member 3 — Limited Seat Auctions. */
import './seat-auction';
/* Member 3 — Paid application unlock (Feature A + B). */
import './paid-application';

/* Member 2 — Module 2 frontend stubs (loaded only where needed). */
import './module2/studies';
import './module2/screener';
import './module2/schedule';
import './module2/pipeline';
import './module2/messaging';
import './module2/notes';
import './module2/tier';