import './bootstrap';
import './api';

/* Member 4 — Smart Participant Matching & invitations.
   Each module no-ops immediately if its hook element is not on the page. */
import './candidates';           // suggested-candidates panel (dashboard + study page)
import './participant-profile';  // the candidate profile page
import './study-match';          // study page: criteria + "your match" + respond
import './invitations';          // the participant's invitations page

/* Member 4 — Referral system. */
import './referrals';            // Refer a Friend (participant + researcher)
import './referral-banner';      // "you were invited by X" on the signup page
