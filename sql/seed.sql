SET NAMES utf8mb4;

INSERT IGNORE INTO locations (name, slug, province, sort) VALUES
('Kathmandu','kathmandu','Bagmati',1),('Lalitpur','lalitpur','Bagmati',2),('Bhaktapur','bhaktapur','Bagmati',3),
('Pokhara','pokhara','Gandaki',4),('Biratnagar','biratnagar','Koshi',5),('Itahari','itahari','Koshi',6),
('Dharan','dharan','Koshi',7),('Birgunj','birgunj','Madhesh',8),('Butwal','butwal','Lumbini',9),
('Bharatpur','bharatpur','Bagmati',10),('Hetauda','hetauda','Bagmati',11),('Janakpur','janakpur','Madhesh',12),
('Nepalgunj','nepalgunj','Lumbini',13),('Dhangadhi','dhangadhi','Sudurpashchim',14),('Damak','damak','Koshi',15),
('Birtamod','birtamod','Koshi',16),('Bhairahawa','bhairahawa','Lumbini',17),('Surkhet','surkhet','Karnali',18),
('Banepa','banepa','Bagmati',19),('Tulsipur','tulsipur','Lumbini',20),('Anywhere in Nepal','anywhere-in-nepal',NULL,99);

INSERT IGNORE INTO locations (name, slug, province, country, sort) VALUES
('Dubai','dubai','Dubai','United Arab Emirates',201),('Abu Dhabi','abu-dhabi','Abu Dhabi','United Arab Emirates',202),('Sharjah','sharjah','Sharjah','United Arab Emirates',203),
('Ajman','ajman','Ajman','United Arab Emirates',204),('Ras Al Khaimah','ras-al-khaimah','Ras Al Khaimah','United Arab Emirates',205),('Fujairah','fujairah','Fujairah','United Arab Emirates',206),
('Umm Al Quwain','umm-al-quwain','Umm Al Quwain','United Arab Emirates',207),('Al Ain','al-ain','Abu Dhabi','United Arab Emirates',208),('Anywhere in UAE','anywhere-in-uae',NULL,'United Arab Emirates',299);

INSERT IGNORE INTO categories (name, slug, icon, sort) VALUES
('IT & Software','it-software','💻',1),('Banking & Finance','banking-finance','🏦',2),('Sales & Marketing','sales-marketing','📈',3),
('Accounting','accounting','🧾',4),('NGO / INGO','ngo-ingo','🤝',5),('Teaching & Education','teaching-education','🎓',6),
('Healthcare','healthcare','🩺',7),('Engineering','engineering','🛠️',8),('Hospitality & Tourism','hospitality-tourism','🏨',9),
('Design & Creative','design-creative','🎨',10),('Customer Service','customer-service','🎧',11),('Human Resources','human-resources','🧑‍💼',12),
('Admin & Office','admin-office','🗂️',13),('Logistics & Driving','logistics-driving','🚚',14),('Construction','construction','🏗️',15),
('Media & Journalism','media-journalism','📰',16),('Legal','legal','⚖️',17),('Manufacturing','manufacturing','🏭',18);

INSERT IGNORE INTO industries (name, slug) VALUES
('Information Technology','information-technology'),('Banking','banking'),('Insurance','insurance'),('Microfinance & Cooperatives','microfinance'),
('NGO / INGO','ngo-ingo'),('Education','education'),('Healthcare','healthcare'),('Hospitality','hospitality'),('Travel & Tourism','travel-tourism'),
('Manufacturing','manufacturing'),('Retail & E-commerce','retail-ecommerce'),('Telecommunication','telecommunication'),
('Construction & Real Estate','construction'),('Media & Advertising','media'),('Government','government'),('Hydropower & Energy','energy'),
('Consulting','consulting'),('Food & Restaurant','food-restaurant'),('Logistics','logistics'),('Other','other');

INSERT IGNORE INTO settings (`key`,`value`) VALUES
('site_name','KaamKhoj'),
('site_tagline','Find your next job in Nepal'),
('site_description','Nepal''s modern job portal. Search thousands of jobs in Kathmandu, Pokhara, Biratnagar and across Nepal. Apply in one click, track every application.'),
('site_keywords','jobs in nepal, job vacancy nepal, kathmandu jobs, nepal job portal, latest jobs nepal'),
('contact_email',''),('contact_phone',''),('contact_address','Nepal'),
('currency','NPR'),('country','Nepal'),
('job_auto_approve','0'),('job_default_days','30'),('employer_require_verify','0'),('require_email_verify','1'),
('mail_from_email',''),('mail_from_name','KaamKhoj'),
('smtp_host',''),('smtp_port','587'),('smtp_user',''),('smtp_pass',''),('smtp_secure','tls'),
('google_client_id',''),('google_client_secret',''),
('microsoft_client_id',''),('microsoft_client_secret',''),('microsoft_tenant','common'),
('ai_enabled','1'),('groq_api_key',''),('groq_model','llama-3.3-70b-versatile'),('ai_daily_limit','30'),
('google_analytics_id',''),('google_site_verification',''),
('facebook_url',''),('linkedin_url',''),('instagram_url',''),
('primary_color','#e11d48'),('cron_key',''),('db_version','5'),('countries','Nepal'),('ai_budget_daily','0.05'),('ai_budget_monthly','1'),('ai_fallback_after','180'),('finder_daily_searches','20'),('ai_provider','groq'),('indexnow_enabled','1'),
('google_enabled','1'),('microsoft_enabled','1'),('allow_seeker_signup','1'),('allow_employer_signup','1'),('password_login','1');

INSERT IGNORE INTO email_templates (`key`, name, subject, body) VALUES
('application_forward','Application forwarded to imported company','New application for {job_title} via {site_name}','<p>Hello {company_name} team,</p><p>A candidate applied for your vacancy <b>{job_title}</b> on <b>{site_name}</b>.</p><p><b>{applicant_name}</b><br>{applicant_email}<br>{applicant_phone}</p><p>{cover}</p><p>Their CV is attached.</p><p>Want to manage applicants, post jobs for free and reply to candidates? <a class="btn" href="{claim_link}">Claim your free company page</a></p>'),
('welcome','Welcome email','Welcome to {site_name}, {name}!','<p>Hi {name},</p><p>Welcome to <b>{site_name}</b>. Your account is ready.</p><p>{role_message}</p><p><a class="btn" href="{dashboard_url}">Go to dashboard</a></p>'),
('verify_email','Email verification','Verify your email for {site_name}','<p>Hi {name},</p><p>Please confirm your email address to activate all features.</p><p><a class="btn" href="{link}">Verify email</a></p><p>This link expires in 48 hours.</p>'),
('password_reset','Password reset','Reset your {site_name} password','<p>Hi {name},</p><p>We received a request to reset your password.</p><p><a class="btn" href="{link}">Reset password</a></p><p>This link expires in 1 hour. If you didn''t ask for this, ignore this email.</p>'),
('login_alert','New login alert','New sign-in to your {site_name} account','<p>Hi {name},</p><p>Your account was just signed in via <b>{method}</b>.</p><p>Device: {device}<br>IP: {ip}<br>Time: {time}</p><p>If this wasn''t you, reset your password immediately.</p>'),
('application_received_seeker','Application sent (to seeker)','You applied for {job_title} at {company_name}','<p>Hi {name},</p><p>Your application for <b>{job_title}</b> at <b>{company_name}</b> was sent successfully.</p><p>You can track its status any time.</p><p><a class="btn" href="{link}">Track application</a></p>'),
('application_received_employer','New applicant (to employer)','New applicant for {job_title}: {applicant_name}','<p>Hi {name},</p><p><b>{applicant_name}</b> just applied for <b>{job_title}</b>. Profile match: <b>{match}%</b>.</p><p><a class="btn" href="{link}">Review applicant</a></p>'),
('application_status','Application status update','Update on your application: {job_title}','<p>Hi {name},</p><p>Your application for <b>{job_title}</b> at <b>{company_name}</b> is now: <b>{status}</b>.</p><p>{message}</p><p><a class="btn" href="{link}">View details</a></p>'),
('job_approved','Job approved','Your job "{job_title}" is live','<p>Hi {name},</p><p>Your job <b>{job_title}</b> is approved and now visible to job seekers.</p><p><a class="btn" href="{link}">View job</a></p>'),
('job_rejected','Job rejected','Your job "{job_title}" needs changes','<p>Hi {name},</p><p>Your job <b>{job_title}</b> was not approved.</p><p>Reason: {reason}</p><p><a class="btn" href="{link}">Edit job</a></p>'),
('job_alert','Job alert digest','{count} new jobs matching your alert','<p>Hi {name},</p><p>Here are new jobs matching <b>{alert}</b>:</p>{jobs}<p><a class="btn" href="{link}">See all jobs</a></p>'),
('new_job_followed','Followed company posted a job','{company_name} is hiring: {job_title}','<p>Hi {name},</p><p><b>{company_name}</b>, a company you follow, just posted <b>{job_title}</b>.</p><p><a class="btn" href="{link}">View job</a></p>'),
('job_expiring','Job expiring soon','Your job "{job_title}" closes in {days} days','<p>Hi {name},</p><p>Your job <b>{job_title}</b> reaches its deadline in {days} days. It has {applications} applications.</p><p><a class="btn" href="{link}">Extend or manage</a></p>'),
('admin_new_job','New job pending (to admin)','Job pending approval: {job_title}','<p>A new job <b>{job_title}</b> by <b>{company_name}</b> is waiting for approval.</p><p><a class="btn" href="{link}">Review</a></p>'),
('contact_admin','Contact form (to admin)','Contact form: {subject}','<p><b>{name}</b> ({email}) wrote:</p><p>{message}</p>');

INSERT IGNORE INTO pages (slug, title, content, meta_description) VALUES
('about','About Us','<h2>Who we are</h2><p>We connect job seekers across Nepal with the companies that need them - faster, simpler and fully transparent. Every application is tracked, every employer has a real profile, and AI tools help both sides write better.</p>','About our job portal'),
('privacy','Privacy Policy','<h2>Your data</h2><p>We store the information you provide (profile, CV, applications) to run the service. CVs are shared only with employers you apply to, or with employers searching candidates if you keep your profile visible. You can delete your account at any time.</p><h2>Logs</h2><p>For security we log sign-ins, IP addresses and key actions.</p>','Privacy policy'),
('terms','Terms of Use','<h2>Using the service</h2><p>Employers must post genuine vacancies. Charging candidates fees for jobs is not allowed. Job seekers must provide truthful information. We may remove content or accounts that break these rules.</p>','Terms of use'),
('employers','For Employers','<h2>Hire faster</h2><p>Post jobs, build your company profile, review applicants in a pipeline, and contact candidates - all in one place.</p>','Post jobs and hire in Nepal');
