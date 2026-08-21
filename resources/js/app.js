import './bootstrap';
import './api';

/* Member 4 — Smart Participant Matching & invitations.
   Each module no-ops immediately if its hook element is not on the page. */
import './candidates';           // suggested-candidates panel (dashboard + study page)
import './participant-profile';  // the candidate profile page
import './study-match';          // study page: criteria + "your match" + respond
import './invitations';          // the participant's invitations page
import './feed';                 // recommendation feed + skill-gap coach

/* Member 4 — Referral system. */
import './referrals';            // Refer a Friend (participant + researcher)
import './referral-banner';      // "you were invited by X" on the signup page

/* Member 3 — Karma Credits. */
import './karma';

/* Member 2 — Module 2 frontend stubs (loaded only where needed). */
import './module2/studies';
import './module2/screener';
import './module2/schedule';
import './module2/pipeline';
import './module2/messaging';
import './module2/notes';
import './module2/tier';