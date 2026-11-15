<?php

/*
 * Reference data for CatalogSeeder: skills and professional roles (job titles) per industry.
 *
 * - `general`: cross-industry entries. They are left untagged, which means "offered for every
 *   industry"; the seeder never tags them even if a name also appears under an industry.
 * - `industries`: keys are the canonical industry names (2026_11_18_000000_install_reference_industries).
 *   A name listed under several industries becomes one entry tagged with all of them.
 * - `aliases`: an existing row whose label matches an alias is reused (and kept as named) instead of
 *   creating a near-duplicate, e.g. the legacy "Devops" row stands in for "DevOps Engineer".
 */

return [
    'aliases' => [
        'DevOps Engineer' => ['Devops', 'DevOps'],
        'Copywriter' => ['Copyrighter'],
        'UI/UX Designer' => ['UI/UX Engineer'],
    ],

    'general' => [
        'skills' => [
            'Communication', 'Teamwork', 'Leadership', 'Problem Solving', 'Critical Thinking', 'Time Management',
            'Project Management', 'Microsoft Excel', 'Microsoft Office', 'Presentation Skills', 'Negotiation',
            'Customer Service', 'Data Analysis', 'Stakeholder Management', 'Report Writing',
        ],
        'roles' => [
            'Project Manager', 'Product Manager', 'Business Analyst', 'Operations Manager', 'Data Analyst',
            'Administrative Assistant', 'Customer Support Specialist', 'Consultant',
        ],
    ],

    'industries' => [
        'Construction' => [
            'skills' => ['AutoCAD', 'Blueprint Reading', 'Construction Estimating', 'Site Safety (OSHA)', 'Building Codes', 'Scheduling (Primavera P6)', 'Quantity Surveying'],
            'roles' => ['Site Engineer', 'Construction Manager', 'Quantity Surveyor', 'Civil Engineer', 'Site Supervisor', 'Estimator'],
        ],
        'Healthcare' => [
            'skills' => ['Patient Care', 'Electronic Health Records (EHR)', 'Medical Terminology', 'HIPAA Compliance', 'Clinical Documentation', 'Triage', 'Phlebotomy'],
            'roles' => ['Registered Nurse', 'Physician', 'Medical Assistant', 'Healthcare Administrator', 'Clinical Coordinator', 'Physiotherapist'],
        ],
        'Finance' => [
            'skills' => ['Financial Modeling', 'Financial Analysis', 'Budgeting & Forecasting', 'Accounting', 'IFRS', 'Risk Management', 'Valuation'],
            'roles' => ['Financial Analyst', 'Accountant', 'Controller', 'Chief Financial Officer', 'Auditor', 'Treasury Analyst'],
        ],
        'Education' => [
            'skills' => ['Curriculum Development', 'Lesson Planning', 'Classroom Management', 'Learning Management Systems', 'Student Assessment', 'Instructional Design'],
            'roles' => ['Teacher', 'Lecturer', 'Instructional Designer', 'Academic Coordinator', 'Tutor', 'School Administrator'],
        ],
        'Government & Public Administration' => [
            'skills' => ['Public Policy', 'Regulatory Compliance', 'Public Procurement', 'Grant Writing', 'Program Evaluation'],
            'roles' => ['Policy Analyst', 'Public Administrator', 'Program Officer', 'Procurement Officer', 'Civil Servant'],
        ],
        'Legal Services' => [
            'skills' => ['Legal Research', 'Contract Drafting', 'Litigation', 'Legal Writing', 'Due Diligence', 'Regulatory Compliance', 'Intellectual Property'],
            'roles' => ['Lawyer', 'Paralegal', 'Legal Counsel', 'Compliance Officer', 'Legal Assistant'],
        ],
        'Technology' => [
            'skills' => ['JavaScript', 'TypeScript', 'Python', 'PHP', 'Laravel', 'React', 'Vue.js', 'Node.js', 'SQL', 'Git', 'Docker', 'Kubernetes', 'AWS', 'REST APIs', 'CI/CD', 'Agile/Scrum'],
            'roles' => ['Backend Developer', 'Frontend Developer', 'Full Stack Developer', 'DevOps Engineer', 'QA Engineer', 'UI/UX Designer', 'Software Architect', 'Mobile Developer', 'Site Reliability Engineer', 'Scrum Master'],
        ],
        'Automotive' => [
            'skills' => ['Automotive Diagnostics', 'Vehicle Maintenance', 'CAD (CATIA)', 'Lean Manufacturing', 'Quality Control', 'Embedded Systems'],
            'roles' => ['Automotive Engineer', 'Automotive Technician', 'Service Advisor', 'Quality Engineer', 'Production Engineer'],
        ],
        'Banking' => [
            'skills' => ['Credit Analysis', 'Anti-Money Laundering (AML)', 'KYC', 'Risk Management', 'Financial Analysis', 'Loan Processing', 'Regulatory Compliance'],
            'roles' => ['Relationship Manager', 'Credit Analyst', 'Bank Teller', 'Loan Officer', 'Compliance Officer', 'Branch Manager'],
        ],
        'Manufacturing' => [
            'skills' => ['Lean Manufacturing', 'Six Sigma', 'Quality Control', 'Production Planning', 'CNC Machining', 'Supply Chain Management', 'Root Cause Analysis'],
            'roles' => ['Production Engineer', 'Quality Engineer', 'Plant Manager', 'Process Engineer', 'Maintenance Technician', 'Production Supervisor'],
        ],
        'Real Estate' => [
            'skills' => ['Property Management', 'Real Estate Valuation', 'Lease Negotiation', 'Market Analysis', 'CRM Software'],
            'roles' => ['Real Estate Agent', 'Property Manager', 'Real Estate Analyst', 'Leasing Consultant', 'Appraiser'],
        ],
        'Community & Social Services' => [
            'skills' => ['Case Management', 'Crisis Intervention', 'Community Outreach', 'Counseling', 'Program Coordination'],
            'roles' => ['Social Worker', 'Case Manager', 'Community Outreach Coordinator', 'Youth Worker', 'Program Coordinator'],
        ],
        'Commercial & Retail' => [
            'skills' => ['Merchandising', 'Inventory Management', 'Point of Sale (POS)', 'Retail Sales', 'Visual Merchandising'],
            'roles' => ['Store Manager', 'Sales Associate', 'Merchandiser', 'Retail Buyer', 'Cashier'],
        ],
        'Energy' => [
            'skills' => ['Power Systems', 'Energy Management', 'Electrical Engineering', 'HSE Compliance', 'SCADA'],
            'roles' => ['Energy Engineer', 'Electrical Engineer', 'Energy Analyst', 'Plant Operator', 'HSE Officer'],
        ],
        'Food & Beverage Manufacturing' => [
            'skills' => ['Food Safety (HACCP)', 'Quality Control', 'Production Planning', 'GMP', 'Food Technology'],
            'roles' => ['Food Technologist', 'Production Supervisor', 'Quality Assurance Officer', 'Plant Manager'],
        ],
        'Travel & Tourism' => [
            'skills' => ['Travel Planning', 'Booking Systems (GDS)', 'Tour Operations', 'Destination Knowledge'],
            'roles' => ['Travel Consultant', 'Tour Guide', 'Travel Agent', 'Tour Operations Manager'],
        ],
        'Telecommunications' => [
            'skills' => ['Network Engineering', 'RF Engineering', '5G/LTE', 'Fiber Optics', 'VoIP', 'Network Security'],
            'roles' => ['Network Engineer', 'RF Engineer', 'Telecom Technician', 'NOC Engineer'],
        ],
        'Architecture & Urban Planning' => [
            'skills' => ['AutoCAD', 'Revit', 'SketchUp', 'Urban Design', 'GIS', 'Building Codes'],
            'roles' => ['Architect', 'Urban Planner', 'Architectural Technologist', 'Landscape Architect'],
        ],
        'Arts, Culture & Entertainment' => [
            'skills' => ['Event Production', 'Art Curation', 'Performance', 'Stage Management', 'Creative Direction'],
            'roles' => ['Curator', 'Event Producer', 'Stage Manager', 'Artist', 'Creative Director'],
        ],
        'Biotechnology & Life Sciences' => [
            'skills' => ['Molecular Biology', 'PCR', 'Cell Culture', 'Bioinformatics', 'GLP', 'Laboratory Techniques'],
            'roles' => ['Research Scientist', 'Lab Technician', 'Bioinformatician', 'Clinical Research Associate'],
        ],
        'Consumer Goods' => [
            'skills' => ['Brand Management', 'Category Management', 'Trade Marketing', 'Demand Planning', 'Market Research'],
            'roles' => ['Brand Manager', 'Category Manager', 'Trade Marketing Manager', 'Demand Planner'],
        ],
        'Cybersecurity' => [
            'skills' => ['Network Security', 'Penetration Testing', 'SIEM', 'Incident Response', 'Threat Intelligence', 'ISO 27001', 'Vulnerability Assessment'],
            'roles' => ['Security Analyst', 'Penetration Tester', 'Security Engineer', 'SOC Analyst', 'Chief Information Security Officer'],
        ],
        'Digital Media & Publishing' => [
            'skills' => ['Copywriting', 'Content Strategy', 'Editing & Proofreading', 'SEO', 'Content Management Systems'],
            'roles' => ['Copywriter', 'Editor', 'Content Writer', 'Content Strategist', 'Journalist'],
        ],
        'E-Commerce & Online Retail' => [
            'skills' => ['Shopify', 'WooCommerce', 'Conversion Rate Optimization', 'Marketplace Management', 'Inventory Management', 'Google Analytics'],
            'roles' => ['E-Commerce Manager', 'Marketplace Specialist', 'E-Commerce Analyst', 'Online Merchandiser'],
        ],
        'Environmental Services & Sustainability' => [
            'skills' => ['Environmental Impact Assessment', 'Sustainability Reporting', 'ESG', 'Waste Management', 'Carbon Accounting'],
            'roles' => ['Environmental Consultant', 'Sustainability Manager', 'Environmental Engineer', 'ESG Analyst'],
        ],
        'Fashion & Apparel' => [
            'skills' => ['Fashion Design', 'Pattern Making', 'Textile Knowledge', 'Trend Forecasting', 'Adobe Illustrator'],
            'roles' => ['Fashion Designer', 'Merchandiser', 'Pattern Maker', 'Stylist', 'Retail Buyer'],
        ],
        'Film, Television & Broadcasting' => [
            'skills' => ['Video Editing', 'Adobe Premiere Pro', 'Cinematography', 'Scriptwriting', 'Broadcast Production'],
            'roles' => ['Video Editor', 'Producer', 'Director', 'Camera Operator', 'Scriptwriter'],
        ],
        'Food & Beverage' => [
            'skills' => ['Food Safety (HACCP)', 'Menu Development', 'Beverage Management', 'Inventory Management'],
            'roles' => ['Food & Beverage Manager', 'Chef', 'Bartender', 'Barista'],
        ],
        'Forestry & Natural Resources' => [
            'skills' => ['Forest Management', 'GIS', 'Conservation', 'Natural Resource Planning'],
            'roles' => ['Forester', 'Conservation Officer', 'Natural Resource Manager', 'GIS Analyst'],
        ],
        'Gaming & Interactive Entertainment' => [
            'skills' => ['Unity', 'Unreal Engine', 'C#', 'C++', 'Game Design', '3D Modeling'],
            'roles' => ['Game Developer', 'Game Designer', '3D Artist', 'Level Designer', 'QA Engineer'],
        ],
        'Hospitality & Accommodations' => [
            'skills' => ['Front Office Operations', 'Property Management Systems (PMS)', 'Guest Relations', 'Housekeeping Management', 'Revenue Management'],
            'roles' => ['Hotel Manager', 'Front Desk Agent', 'Guest Relations Manager', 'Housekeeping Supervisor', 'Revenue Manager'],
        ],
        'Human Resources & Staffing' => [
            'skills' => ['Recruitment', 'Talent Acquisition', 'Onboarding', 'Payroll', 'Employee Relations', 'HRIS'],
            'roles' => ['HR Generalist', 'Recruiter', 'Talent Acquisition Specialist', 'HR Manager', 'Payroll Specialist'],
        ],
        'Insurance' => [
            'skills' => ['Underwriting', 'Claims Management', 'Actuarial Analysis', 'Risk Assessment'],
            'roles' => ['Underwriter', 'Claims Adjuster', 'Actuary', 'Insurance Agent'],
        ],
        'International Trade & Commerce' => [
            'skills' => ['Import/Export Regulations', 'Customs Compliance', 'Incoterms', 'Trade Finance'],
            'roles' => ['Trade Compliance Specialist', 'Export Manager', 'Customs Broker', 'International Sales Manager'],
        ],
        'Logistics & Supply Chain' => [
            'skills' => ['Supply Chain Management', 'Warehouse Management', 'Inventory Management', 'Procurement', 'Fleet Management', 'SAP'],
            'roles' => ['Logistics Coordinator', 'Supply Chain Manager', 'Warehouse Manager', 'Procurement Specialist', 'Demand Planner'],
        ],
        'Nonprofit & Philanthropy' => [
            'skills' => ['Fundraising', 'Grant Writing', 'Volunteer Management', 'Donor Relations', 'Program Evaluation'],
            'roles' => ['Fundraising Manager', 'Program Officer', 'Volunteer Coordinator', 'Grant Writer'],
        ],
        'Pharmaceuticals' => [
            'skills' => ['GMP', 'Regulatory Affairs', 'Pharmacovigilance', 'Clinical Trials', 'Quality Assurance'],
            'roles' => ['Pharmacist', 'Regulatory Affairs Specialist', 'Medical Representative', 'Clinical Research Associate', 'Quality Assurance Officer'],
        ],
        'Professional & Business Services' => [
            'skills' => ['Management Consulting', 'Process Improvement', 'Business Strategy', 'Financial Analysis', 'Change Management'],
            'roles' => ['Management Consultant', 'Strategy Analyst', 'Business Development Manager', 'Executive Assistant'],
        ],
        'Public Relations & Communications' => [
            'skills' => ['Media Relations', 'Press Releases', 'Crisis Communication', 'Copywriting', 'Social Media Management'],
            'roles' => ['PR Specialist', 'Communications Manager', 'Copywriter', 'Spokesperson'],
        ],
        'Renewable Energy' => [
            'skills' => ['Solar PV Design', 'Wind Energy Systems', 'Energy Storage', 'Energy Management', 'Electrical Engineering'],
            'roles' => ['Solar Engineer', 'Renewable Energy Analyst', 'Wind Turbine Technician', 'Energy Engineer'],
        ],
        'Research & Development' => [
            'skills' => ['Research Methodology', 'Statistical Analysis', 'Technical Writing', 'Prototyping', 'Laboratory Techniques'],
            'roles' => ['Research Scientist', 'R&D Engineer', 'Research Assistant', 'Product Development Engineer'],
        ],
        'Restaurants & Food Services' => [
            'skills' => ['Food Preparation', 'Food Safety (HACCP)', 'Menu Development', 'Kitchen Management'],
            'roles' => ['Chef', 'Restaurant Manager', 'Line Cook', 'Waiter', 'Barista'],
        ],
        'Robotics & Automation' => [
            'skills' => ['PLC Programming', 'ROS', 'Industrial Automation', 'Embedded Systems', 'Control Systems', 'C++'],
            'roles' => ['Robotics Engineer', 'Automation Engineer', 'Controls Engineer', 'Mechatronics Engineer'],
        ],
        'Science & Scientific Research' => [
            'skills' => ['Research Methodology', 'Statistical Analysis', 'Laboratory Techniques', 'Scientific Writing', 'Python'],
            'roles' => ['Research Scientist', 'Lab Technician', 'Research Assistant', 'Data Scientist'],
        ],
        'Sports & Recreation' => [
            'skills' => ['Coaching', 'Fitness Training', 'Sports Management', 'Event Management'],
            'roles' => ['Coach', 'Personal Trainer', 'Sports Manager', 'Recreation Coordinator'],
        ],
        'Venture Capital & Private Equity' => [
            'skills' => ['Financial Modeling', 'Valuation', 'Due Diligence', 'Deal Sourcing', 'Portfolio Management'],
            'roles' => ['Investment Analyst', 'Associate (Venture Capital)', 'Portfolio Manager', 'Investment Partner'],
        ],
        'Web3, Blockchain & Digital Assets' => [
            'skills' => ['Solidity', 'Smart Contracts', 'Ethereum', 'Web3.js', 'Blockchain Architecture', 'Tokenomics'],
            'roles' => ['Blockchain Developer', 'Smart Contract Auditor', 'Web3 Product Manager', 'Crypto Analyst'],
        ],
        'Design & Creative Services' => [
            'skills' => ['Figma', 'Adobe Photoshop', 'Adobe Illustrator', 'Graphic Design', 'Branding', 'Typography', 'Copywriting'],
            'roles' => ['Graphic Designer', 'UI/UX Designer', 'Art Director', 'Copywriter', 'Illustrator'],
        ],
        'Software & Technology' => [
            'skills' => ['JavaScript', 'TypeScript', 'Python', 'Java', 'PHP', 'Laravel', 'React', 'Node.js', 'SQL', 'Git', 'Docker', 'AWS', 'REST APIs', 'Automated Testing', 'Agile/Scrum'],
            'roles' => ['Backend Developer', 'Frontend Developer', 'Full Stack Developer', 'DevOps Engineer', 'QA Engineer', 'UI/UX Designer', 'Software Engineer', 'Engineering Manager'],
        ],
        'Marketing & Advertising' => [
            'skills' => ['Digital Marketing', 'SEO', 'Google Ads', 'Social Media Marketing', 'Content Marketing', 'Copywriting', 'Email Marketing', 'Marketing Analytics'],
            'roles' => ['Marketing Manager', 'Digital Marketing Specialist', 'Copywriter', 'SEO Specialist', 'Social Media Manager', 'Content Marketer'],
        ],
        'Sales & Business Development' => [
            'skills' => ['B2B Sales', 'Lead Generation', 'CRM (Salesforce, HubSpot)', 'Sales Prospecting', 'Account Management'],
            'roles' => ['Sales Representative', 'Account Executive', 'Business Development Manager', 'Sales Manager', 'Account Manager'],
        ],
        'Artificial Intelligence & Machine Learning' => [
            'skills' => ['Machine Learning', 'Deep Learning', 'Python', 'TensorFlow', 'PyTorch', 'Natural Language Processing', 'Computer Vision', 'MLOps'],
            'roles' => ['Machine Learning Engineer', 'Data Scientist', 'AI Researcher', 'MLOps Engineer'],
        ],
        'Mental & Behavioral Health' => [
            'skills' => ['Counseling', 'Cognitive Behavioral Therapy', 'Crisis Intervention', 'Case Management', 'Clinical Documentation'],
            'roles' => ['Psychologist', 'Counselor', 'Psychiatric Nurse', 'Behavioral Therapist'],
        ],
        'Childcare & Early Childhood Services' => [
            'skills' => ['Early Childhood Education', 'Child Development', 'Child Safety & First Aid', 'Lesson Planning'],
            'roles' => ['Early Childhood Educator', 'Childcare Worker', 'Nanny', 'Preschool Teacher'],
        ],
    ],
];
