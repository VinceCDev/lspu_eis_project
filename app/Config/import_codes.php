<?php

/**
 * Program-code lookup for the "Data on Employment" (tracer) Excel import
 * — see App\Services\EmploymentReportImporter.
 *
 * Campus and graduation year are chosen in the import dialog, not read
 * from the sheet, so only program codes need mapping here.
 *
 * Keys are matched case-insensitively with every non-alphanumeric
 * character removed ("BSIT-AMG", "bsit amg", "BsitAmg" all match key
 * "BSITAMG"). Values MUST match a course name in
 * app/Config/campus_programs.php so the college resolves automatically.
 * An unrecognised code still imports the graduate, but keeps the raw code
 * as the course with a blank college and is listed under "warnings" —
 * add any missing codes below.
 */

return [
    'program' => [
        // --- International Hospitality & Tourism ---
        'BSTM' => 'BS Tourism Management',
        'BSHM' => 'BS Hospitality Management',
        'BSHRM' => 'BS Hotel and Restaurant Management',

        // --- Computer Studies ---
        'BSIT' => 'BS Information Technology',
        'BSITAMG' => 'BS Information Technology (Animation and Motion Graphics)',
        'BSITSMP' => 'BS Information Technology (Service Management Program)',
        'BSITWMAD' => 'BS Information Technology (Web and Mobile Application Development)',
        'BSCS' => 'BS Computer Science',
        'BSCSGV' => 'BS Computer Science (Graphics and Visualization)',
        'BSIS' => 'BS Information System',
        'MIT' => 'Master in Information Technology',
        'MSIT' => 'MS Information Technology',

        // --- Engineering ---
        'BSCE' => 'BS Civil Engineering',
        'BSEE' => 'BS Electrical Engineering',
        'EE' => 'BS Electrical Engineering',
        'BSECE' => 'BS Electronics Engineering',
        'ECE' => 'BS Electronics Engineering',
        'BSCPE' => 'BS Computer Engineering',
        'CPE' => 'BS Computer Engineering',
        'BSME' => 'BS Mechanical Engineering',
        'BSABE' => 'BS Agricultural and Biosystems Engineering',

        // --- Industrial Technology (note: "BSIT-xx" specialisations here
        //     are Industrial Technology, distinct from Information Tech) ---
        'BSINDTECH' => 'BS Industrial Technology',
        'BSITADT' => 'BS Industrial Technology (Architectural Drafting)',
        'BSITAT' => 'BS Industrial Technology (Automotive Technology)',
        'BSITELT' => 'BS Industrial Technology (Electrical Technology)',
        'BSITELX' => 'BS Industrial Technology (Electronics Technology)',
        'BSITFBPSM' => 'BS Industrial Technology (Food and Beverage Preparation and Service Management Technology)',
        'BSITHVACR' => 'BS Industrial Technology (Heating, Ventilating, Air-Conditioning and Refrigeration Technology)',
        'ARCHDRAFTING' => 'BS Industrial Technology (Architectural Drafting)',
        'AUTOMOTIVE' => 'BS Industrial Technology (Automotive Technology)',
        'ELECTRICAL' => 'BS Industrial Technology (Electrical Technology)',
        'ELECTRONICS' => 'BS Industrial Technology (Electronics Technology)',

        // --- Business Administration & Accountancy ---
        'BSA' => 'BS Accountancy',
        'BSACT' => 'BS Accountancy',
        'BSBA' => 'BS Business Administration',
        'BSBAFM' => 'BS Business Administration (Financial Management)',
        'BSBAMM' => 'BS Business Administration (Marketing Management)',
        'BSOA' => 'BS Office Administration',
        'BSOALOP' => 'BS Office Administration (Legal Office Procedure)',
        'BSOAMOP' => 'BS Office Administration (Medical Office Procedure)',
        'AOA' => 'BS Office Administration',
        'BSENTREP' => 'BS Entrepreneurship',

        // --- Arts & Sciences ---
        'BSBIO' => 'BS Biology',
        'BSBIOLOGY' => 'BS Biology',
        'BSCHEM' => 'BS Chemistry',
        'BSMATH' => 'BS Mathematics',
        'BSPSYCH' => 'BS Psychology',
        'BSPSY' => 'BS Psychology',
        'BSPSYCHOLOGY' => 'BS Psychology',
        'ABBROAD' => 'BA Broadcasting',
        'BABROAD' => 'BA Broadcasting',

        // --- Criminal Justice ---
        'BSCRIM' => 'BS Criminology',

        // --- Agriculture / Fisheries / Food-Nutrition ---
        'BSAGRI' => 'BS Agriculture (Major in Crop Science)',
        'BSAB' => 'BS Agricultural Business',
        'BSFI' => 'BS Fisheries',
        'BSFT' => 'BS Food Technology',
        'FOODTECH' => 'BS Food Technology',
        'BSND' => 'BS Nutrition and Dietetics',
        'BSN' => 'BS Nursing',

        // --- Teacher Education ---
        'BSED' => 'Bachelor of Secondary Education',
        'BSEDENGLISH' => 'Bachelor of Secondary Education (English)',
        'BSEDENG' => 'Bachelor of Secondary Education (English)',
        'BSEDFILIPINO' => 'Bachelor of Secondary Education (Filipino)',
        'BSEDFIL' => 'Bachelor of Secondary Education (Filipino)',
        'BSEDMATH' => 'Bachelor of Secondary Education (Mathematics)',
        'BSEDMATHEMATICS' => 'Bachelor of Secondary Education (Mathematics)',
        'BSEDSCIENCE' => 'Bachelor of Secondary Education (Science)',
        'BSEDSCI' => 'Bachelor of Secondary Education (Science)',
        'BSEDGENERALSCIENCE' => 'Bachelor of Secondary Education (General Science)',
        'BSEDSOCSTUD' => 'Bachelor of Secondary Education (Social Studies)',
        'BSEDSOCIALSTUDIES' => 'Bachelor of Secondary Education (Social Studies)',
        'BSEDSOCSCIENCE' => 'Bachelor of Secondary Education (Social Science)',
        'BSEDSOCSCI' => 'Bachelor of Secondary Education (Social Science)',
        'BSEDVALUESEDUCATION' => 'Bachelor of Secondary Education (Values Education)',
        'BSEDMAPEH' => 'Bachelor of Secondary Education (MAPEH)',
        'BSEDTLE' => 'Bachelor of Secondary Education (Technology & Livelihood Education)',
        'BEED' => 'Bachelor of Elementary Education',
        'BECED' => 'Bachelor of Early Childhood Education',
        'BPED' => 'Bachelor of Physical Education',
        'BTVTED' => 'Bachelor of Technical-Vocational Teacher Education',
        'BTVTEDFSM' => 'Bachelor of Technical-Vocational Teacher Education (Food and Service Management)',
        'BTVTEDGFD' => 'Bachelor of Technical-Vocational Teacher Education (Garments, Fashion and Design)',
        'BTVTEDELT' => 'Bachelor of Technical-Vocational Teacher Education (Electrical Technology)',
        'BTVTEDELX' => 'Bachelor of Technical-Vocational Teacher Education (Electronics Technology)',
        'BTVTEDACP' => 'Bachelor of Technical-Vocational Teacher Education (Agricultural Crops Production)',
        'BTLED' => 'Bachelor of Technology and Livelihood Education',
        'BTLEDHE' => 'Bachelor of Technology and Livelihood Education (Home Economics)',
        'BTLEDIA' => 'Bachelor of Technology and Livelihood Education (Industrial Arts)',
        'INDUSTIALARTS' => 'Bachelor of Technology and Livelihood Education (Industrial Arts)',
        'INDUSTRIALARTS' => 'Bachelor of Technology and Livelihood Education (Industrial Arts)',
        'MAEDENG' => 'Master of Arts in Education (English)',
        'MAEDENGLISH' => 'Master of Arts in Education (English)',
        'MAEDFIL' => 'Master of Arts in Education (Filipino)',
        'MAEDFILIPINO' => 'Master of Arts in Education (Filipino)',
        'MAEDMATH' => 'Master of Arts in Education (Mathematics)',
        'MAEDST' => 'Master of Arts in Education (Science and Technology)',
        'MAEDSCIENCEANDTECHNOLOGY' => 'Master of Arts in Education (Science and Technology)',
        'MAEDSS' => 'Master of Arts in Education (Social Science)',
        'MAEDSOCIALSCIENCE' => 'Master of Arts in Education (Social Science)',
        'MAEDPE' => 'Master of Arts in Education (Physical Education)',
        'MAEDGC' => 'Master of Arts in Education (Guidance and Counseling)',
        'MAEDTHE' => 'Master of Arts in Education (Technology and Home Economics)',
    ],

    /*
     * Keyword fallback for program labels that are not an exact code above (typos, abbreviations, free text typed on the
     * tracer sheet). Tried in order, only when the exact lookup fails. Each rule: [regex, course, college|null].
     * The regex runs against the label upper-cased with punctuation turned into single spaces ("BSEd - Social Science"
     * -> "BSED SOCIAL SCIENCE"). college null = derive it from the course via campus_programs.php.
     */
    'program_rules' => [
        // --- Teacher Education (BTVTEd / old BSIE Industrial Education / BSEd / BEEd / CTP) ---
        ['/^BSIE\b.*\bHE\b|^BSIE\b.*HOME ECON/', 'Bachelor of Technology and Livelihood Education (Home Economics)', null],
        ['/^(BTVTED?|BSIE)\b.*ELECTRIC/', 'Bachelor of Technical-Vocational Teacher Education (Electrical Technology)', null],
        ['/^(BTVTED?|BSIE)\b.*ELECTRO/', 'Bachelor of Technical-Vocational Teacher Education (Electronics Technology)', null],
        ['/^(BTVTED?|BSIE)\b.*(FOOD|FSM)/', 'Bachelor of Technical-Vocational Teacher Education (Food and Service Management)', null],
        ['/^(BTVTED?|BSIE)\b.*(GARMENT|FASHION)/', 'Bachelor of Technical-Vocational Teacher Education (Garments, Fashion and Design)', null],
        ['/^(BTVTED?|BSIE)\b.*(AGRI|CROP)/', 'Bachelor of Technical-Vocational Teacher Education (Agricultural Crops Production)', null],
        ['/^(BTVTED?|BSIE)\b/', 'Bachelor of Technical-Vocational Teacher Education', null],
        ['/^BS ?ED(UC\w*)?\b.*SOC/', 'Bachelor of Secondary Education (Social Science)', null],
        ['/^BS ?ED(UC\w*)?\b.*ENG/', 'Bachelor of Secondary Education (English)', null],
        ['/^BS ?ED(UC\w*)?\b.*FIL/', 'Bachelor of Secondary Education (Filipino)', null],
        ['/^BS ?ED(UC\w*)?\b.*MATH/', 'Bachelor of Secondary Education (Mathematics)', null],
        ['/^BS ?ED(UC\w*)?\b.*(SCI|BIO|CHEM|PHYS)/', 'Bachelor of Secondary Education (Science)', null],
        ['/^BS ?ED(UC\w*)?\b.*MAPEH/', 'Bachelor of Secondary Education (MAPEH)', null],
        ['/^BS ?ED(UC\w*)?\b.*VALUES/', 'Bachelor of Secondary Education (Values Education)', null],
        ['/^BS ?ED(UC\w*)?\b.*(TLE|LIVELIHOOD)/', 'Bachelor of Secondary Education (Technology & Livelihood Education)', null],
        ['/^BS ?ED(UC\w*)?\b/', 'Bachelor of Secondary Education', null],
        ['/^(BEED|BS ?ELEM\w*)\b/', 'Bachelor of Elementary Education', null],
        ['/^CTP$|CERTIFICATE.*TEACH/', 'Certificate in Teaching Program', 'College of Teacher Education'],

        // --- Arts & Sciences / Computer Studies ---
        ['/PSYCH|PYCH/', 'BS Psychology', null],
        ['/^BS ?INFO(RMATION)?( TECH\w*)?$/', 'BS Information Technology', null],

        // --- Hospitality & Tourism ---
        ['/TOURISM/', 'BS Tourism Management', null],
        ['/HOTEL|HRM/', 'BS Hotel and Restaurant Management', null],
        ['/HOSPITALITY/', 'BS Hospitality Management', null],

        // --- Industrial Technology specialisations typed without the BSIT prefix ---
        ['/ARCHI\w*.*DRAFT|^DRAFTING/', 'BS Industrial Technology (Architectural Drafting)', null],
        ['/AUTOMOTIVE/', 'BS Industrial Technology (Automotive Technology)', null],
        ['/^ELECTRIC/', 'BS Industrial Technology (Electrical Technology)', null],
        ['/^ELECTRO/', 'BS Industrial Technology (Electronics Technology)', null],
        ['/^RAC$|HVAC|REFRIG/', 'BS Industrial Technology (Heating, Ventilating, Air-Conditioning and Refrigeration Technology)', null],
    ],
];
