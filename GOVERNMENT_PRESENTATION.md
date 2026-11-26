# VidyaSetu – School Management Portal
### Proposal / presentation notes for Government & Education Department

> Placeholder name "VidyaSetu" can be changed in `config.php`. Demo data is fictional.

## 1. Problem
- Attendance is recorded once a day on paper; period-wise bunking is invisible.
- Parents learn about absences late or never.
- Homework and notices reach students through informal chat groups.
- Attendance percentage and reports are calculated by hand; inspections need paper registers.
- A teacher taking classes in several sections has no single view of their day.

## 2. Solution (what is built and working today)
| Role | What they get |
|---|---|
| **Super Admin** (platform) | Verify / approve / suspend schools, create schools, all-user view with password reset, blog + careers + contact CMS, platform-wide e-mail log and **audit log** |
| **School Admin** | Classes & sections, subjects, faculty, students (single + **CSV bulk import**), **timetable builder with teacher-clash detection**, attendance reports (class + date range, **CSV export**), absentee list per day, notices, homework overview, e-mail log |
| **Faculty** | One dashboard with all periods of the day across different classes; mark P/A/L per period; edit up to 7 days back; upload homework with attachment; weekly timetable; class reports |
| **Student** | Own attendance – subject-wise & date-wise, % with 75% warning; homework with downloads; timetable; notices |
| **Parent** | One login for all children; attendance, homework, timetable, notices; copy of every alert received |

**Automatic parent alert:** when a student is marked absent, the parent receives an e-mail (per period or once per day – configurable). Duplicate mails are prevented.

## 3. Why it is trustworthy (security & privacy)
- bcrypt password hashing, strength policy, **account lockout** after 5 failed logins, forced password change on first login
- **Role-based access**; every school's data is isolated (school-level tenancy)
- CSRF tokens on every form, prepared SQL statements, output escaping, security headers (CSP, X-Frame-Options, nosniff)
- File uploads: extension + size whitelist, random file names, stored **outside web root**, served only to authorised users
- **Audit log** of logins, failed logins, resets, exports and key changes
- Data minimisation: no Aadhaar, no biometrics, no advertising, no data sharing
- Designed to support obligations of the **DPDP Act 2023** (children's data) – see `/privacy` and `/compliance`

## 4. Alignment with national priorities (design alignment, not certification)
- **NEP 2020** – technology in administration, parent involvement
- **Digital India** – paperless attendance & communication, mobile-friendly
- **UDISE+** – school's UDISE+ code stored; attendance data exportable
- **Minimum attendance rule** – automatic % tracking vs configurable threshold
- **Samagra Shiksha monitoring** – reliable, time-stamped, period-wise evidence

## 5. Impact / KPIs to promise
- Same-day parent notification for 100% of recorded absences
- Teachers' attendance time < 1 minute per period
- Zero manual percentage calculation; one-click CSV reports
- Onboarding of a school (data import + timetable) within one day

## 6. Honest status & roadmap (say this – it builds credibility)
**Current:** working prototype / pilot-ready on a single server (PHP + SQLite).
**Before large-scale rollout:**
1. Move database to PostgreSQL/MySQL; host in India (government cloud / MeghRaj / NIC-approved) 
2. Third-party **security audit** (CERT-In empanelled auditor), VAPT, load testing
3. SMS & WhatsApp alerts (many parents do not use e-mail) and Hindi / regional-language UI
4. Mobile app (PWA first), biometric/QR-based attendance option
5. Exams & marks module, fee module, leave application, teacher substitution
6. Integration with UDISE+/state portals, SSO (DigiLocker / state SSO), two-factor login for admins
7. Automated backups, monitoring, SLA and helpdesk

## 7. Suggested pilot proposal
- 5–10 schools in one district for one term (3 months)
- Free for pilot; success measured by attendance-capture rate, parent alert delivery, teacher satisfaction
- Data hosted in India; school owns its data; export on request

## 8. Live demo script (10 minutes)
1. Home page → Register School → (Super Admin) approve it.
2. Login **Faculty** (anita.verma@dps.test): show today's periods across 3 classes → mark attendance, mark 2 absent.
3. Login **School Admin**: Email log shows the parent alerts; attendance report + CSV; timetable builder (try creating a teacher clash).
4. Login **Parent** (parent1@example.test): child dashboard, alerts received.
5. Login **Student** (DPS-6A01): subject-wise attendance, homework.
6. **Super Admin**: audit log, schools list, blog/careers manager.
Demo accounts share one password: `demo_password` in `config.php` (it is shown on the login page in demo mode).
