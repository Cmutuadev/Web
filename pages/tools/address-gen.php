<?php
require_once __DIR__ . "/../../includes/config.php";
if (!isLoggedIn()) { 
    header('Location: /login.php'); 
    exit; 
}

$pageTitle = "Address Generator";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | APPROVED CHECKER</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --bg: #0a0a0f; --card: #111114; --border: #1e1e24; --text: #ffffff; --text-muted: #6b6b76; --primary: #8b5cf6; --success: #10b981; --danger: #ef4444; --warning: #f59e0b; --info: #3b82f6; }
        [data-theme="light"] { --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --text: #0f172a; --text-muted: #64748b; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); font-size: 12px; }
        
        .navbar { position: fixed; top: 0; left: 0; right: 0; height: 50px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 1rem; z-index: 100; }
        .menu-btn { background: none; border: none; color: var(--text); font-size: 0.9rem; cursor: pointer; display: none; }
        .logo-icon { width: 28px; height: 28px; background: linear-gradient(135deg, var(--primary), #06b6d4); border-radius: 6px; display: flex; align-items: center; justify-content: center; }
        .logo-icon i { color: white; font-size: 0.8rem; }
        .logo-text span:first-child { font-weight: 700; font-size: 0.8rem; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .logo-text span:last-child { font-size: 0.55rem; color: var(--text-muted); display: block; }
        .user-menu { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.2rem 0.6rem; border-radius: 2rem; background: var(--bg); border: 1px solid var(--border); }
        .user-avatar { width: 26px; height: 26px; background: linear-gradient(135deg, var(--primary), #7c3aed); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.65rem; color: white; }
        .theme-btn { background: none; border: 1px solid var(--border); border-radius: 0.3rem; padding: 0.2rem 0.4rem; cursor: pointer; color: var(--text-muted); font-size: 0.7rem; }
        .sidebar { position: fixed; left: 0; top: 50px; bottom: 0; width: 240px; background: var(--card); border-right: 1px solid var(--border); transform: translateX(-100%); transition: transform 0.2s; z-index: 99; overflow-y: auto; }
        .sidebar.open { transform: translateX(0); }
        .main { margin-left: 0; margin-top: 50px; padding: 1rem; transition: margin-left 0.2s; }
        .main.sidebar-open { margin-left: 240px; }
        @media (max-width: 768px) { .menu-btn { display: block; } .main.sidebar-open { margin-left: 0; } }
        .container { max-width: 1400px; margin: 0 auto; }
        .page-header { margin-bottom: 1rem; }
        .page-title { font-size: 1.2rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-subtitle { color: var(--text-muted); font-size: 0.65rem; margin-top: 0.2rem; }
        
        .generator-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; }
        .section-title { font-size: 0.75rem; font-weight: 600; margin-bottom: 0.6rem; display: flex; align-items: center; gap: 0.4rem; }
        
        .input-group { margin-bottom: 0.6rem; }
        .input-group label { display: block; font-size: 0.55rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.15rem; font-weight: 600; }
        .input-group select, .input-group input { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.35rem 0.5rem; color: var(--text); font-size: 0.7rem; }
        
        .btn { padding: 0.35rem 0.7rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.65rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-sm { padding: 0.2rem 0.45rem; font-size: 0.6rem; }
        
        .profile-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 0.8rem; margin-bottom: 0.8rem; text-align: center; }
        .profile-avatar { width: 50px; height: 50px; border-radius: 50%; margin: 0 auto 0.5rem; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 600; background: linear-gradient(135deg, var(--primary), #06b6d4); }
        .profile-name { font-size: 0.8rem; font-weight: 600; margin-bottom: 0.2rem; }
        .profile-email { font-size: 0.55rem; color: var(--text-muted); word-break: break-all; }
        
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.4rem; margin-bottom: 0.5rem; }
        .info-item { background: var(--bg); padding: 0.3rem 0.5rem; border-radius: 0.4rem; border: 1px solid var(--border); }
        .info-label { font-size: 0.5rem; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.1rem; }
        .info-value { font-size: 0.65rem; font-weight: 500; font-family: monospace; }
        
        .address-box { background: var(--bg); padding: 0.5rem; border-radius: 0.4rem; border-left: 3px solid var(--primary); margin-top: 0.5rem; }
        .address-text { font-size: 0.6rem; line-height: 1.4; font-family: monospace; }
        
        .results-table { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; overflow: hidden; margin-top: 1rem; }
        .results-header { padding: 0.5rem 0.8rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .results-header h3 { font-size: 0.7rem; font-weight: 600; }
        .result-item { padding: 0.4rem 0.8rem; border-bottom: 1px solid var(--border); display: grid; grid-template-columns: 30px 1fr 80px; gap: 0.5rem; font-size: 0.65rem; align-items: center; }
        .result-item.header { background: var(--bg); font-weight: 600; color: var(--text-muted); }
        .result-avatar { width: 24px; height: 24px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 0.55rem; font-weight: 600; color: white; }
        
        .stats { display: flex; gap: 0.5rem; margin-bottom: 0.8rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.3rem 0.5rem; text-align: center; flex: 1; }
        .stat-number { font-size: 0.85rem; font-weight: 700; }
        .stat-label { font-size: 0.5rem; color: var(--text-muted); }
        
        .copy-icon { cursor: pointer; color: var(--text-muted); margin-left: 0.2rem; font-size: 0.55rem; }
        .copy-icon:hover { color: var(--primary); }
        
        .layout-2col { display: grid; grid-template-columns: 280px 1fr; gap: 1rem; }
        @media (max-width: 768px) { .layout-2col { grid-template-columns: 1fr; } }
        
        .flag { font-size: 0.8rem; margin-right: 0.2rem; }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Address Generator</h1>
                <p class="page-subtitle">Generate realistic fake addresses for 200+ countries</p>
            </div>
            
            <div class="stats">
                <div class="stat-card"><div class="stat-number" id="genCount">0</div><div class="stat-label">Generated</div></div>
                <div class="stat-card"><div class="stat-number" id="quality">-</div><div class="stat-label">Data Quality</div></div>
            </div>
            
            <div class="layout-2col">
                <div class="generator-section">
                    <div class="section-title"><i class="fas fa-globe"></i> Settings</div>
                    
                    <div class="input-group">
                        <label>Country</label>
                        <select id="country">
                            <option value="US">🇺🇸 United States</option>
                            <option value="GB">🇬🇧 United Kingdom</option>
                            <option value="CA">🇨🇦 Canada</option>
                            <option value="AU">🇦🇺 Australia</option>
                            <option value="DE">🇩🇪 Germany</option>
                            <option value="FR">🇫🇷 France</option>
                            <option value="IT">🇮🇹 Italy</option>
                            <option value="ES">🇪🇸 Spain</option>
                            <option value="JP">🇯🇵 Japan</option>
                            <option value="CN">🇨🇳 China</option>
                            <option value="IN">🇮🇳 India</option>
                            <option value="BR">🇧🇷 Brazil</option>
                            <option value="KE">🇰🇪 Kenya</option>
                            <option value="NG">🇳🇬 Nigeria</option>
                            <option value="ZA">🇿🇦 South Africa</option>
                            <option value="EG">🇪🇬 Egypt</option>
                            <option value="AE">🇦🇪 UAE</option>
                            <option value="RU">🇷🇺 Russia</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>Quantity</label>
                        <select id="quantity">
                            <option value="1">1</option>
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                        <button class="btn btn-primary" id="generateBtn"><i class="fas fa-random"></i> Generate</button>
                        <button class="btn btn-secondary" id="clearBtn"><i class="fas fa-trash"></i> Clear</button>
                        <button class="btn btn-success" id="copyAllBtn"><i class="fas fa-copy"></i> Copy All</button>
                    </div>
                </div>
                
                <div class="generator-section" id="previewCard">
                    <div class="section-title"><i class="fas fa-user"></i> Preview</div>
                    <div id="previewContent">
                        <div class="profile-card">
                            <div class="profile-avatar"><i class="fas fa-user"></i></div>
                            <div class="profile-name">John Doe</div>
                            <div class="profile-email">john.doe@example.com</div>
                        </div>
                        <div class="info-grid">
                            <div class="info-item"><div class="info-label">Phone</div><div class="info-value">+1 (555) 123-4567</div></div>
                            <div class="info-item"><div class="info-label">DOB</div><div class="info-value">01/15/1990</div></div>
                        </div>
                        <div class="address-box">
                            <div class="address-text">123 Main Street<br>Apt 4B<br>New York, NY 10001<br>USA</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="results-table">
                <div class="results-header">
                    <h3><i class="fas fa-list"></i> Generated Addresses (<span id="resultCount">0</span>)</h3>
                </div>
                <div id="resultsContainer">
                    <div class="result-item header">
                        <div></div>
                        <div>Name / Address</div>
                        <div>Action</div>
                    </div>
                    <div class="result-item"><div colspan="3" style="text-align: center; color: var(--text-muted); padding: 1rem;">Select country and click Generate</div></div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        let generatedAddresses = [];
        
        // Comprehensive country data with names, flags, and phone codes
        const countryData = {
            'US': { name: 'United States', flag: '🇺🇸', phoneCode: '1', currency: 'USD', locale: 'en_US' },
            'GB': { name: 'United Kingdom', flag: '🇬🇧', phoneCode: '44', currency: 'GBP', locale: 'en_GB' },
            'CA': { name: 'Canada', flag: '🇨🇦', phoneCode: '1', currency: 'CAD', locale: 'en_CA' },
            'AU': { name: 'Australia', flag: '🇦🇺', phoneCode: '61', currency: 'AUD', locale: 'en_AU' },
            'DE': { name: 'Germany', flag: '🇩🇪', phoneCode: '49', currency: 'EUR', locale: 'de_DE' },
            'FR': { name: 'France', flag: '🇫🇷', phoneCode: '33', currency: 'EUR', locale: 'fr_FR' },
            'IT': { name: 'Italy', flag: '🇮🇹', phoneCode: '39', currency: 'EUR', locale: 'it_IT' },
            'ES': { name: 'Spain', flag: '🇪🇸', phoneCode: '34', currency: 'EUR', locale: 'es_ES' },
            'JP': { name: 'Japan', flag: '🇯🇵', phoneCode: '81', currency: 'JPY', locale: 'ja_JP' },
            'CN': { name: 'China', flag: '🇨🇳', phoneCode: '86', currency: 'CNY', locale: 'zh_CN' },
            'IN': { name: 'India', flag: '🇮🇳', phoneCode: '91', currency: 'INR', locale: 'en_IN' },
            'BR': { name: 'Brazil', flag: '🇧🇷', phoneCode: '55', currency: 'BRL', locale: 'pt_BR' },
            'KE': { name: 'Kenya', flag: '🇰🇪', phoneCode: '254', currency: 'KES', locale: 'en_KE' },
            'NG': { name: 'Nigeria', flag: '🇳🇬', phoneCode: '234', currency: 'NGN', locale: 'en_NG' },
            'ZA': { name: 'South Africa', flag: '🇿🇦', phoneCode: '27', currency: 'ZAR', locale: 'en_ZA' },
            'EG': { name: 'Egypt', flag: '🇪🇬', phoneCode: '20', currency: 'EGP', locale: 'ar_EG' },
            'AE': { name: 'UAE', flag: '🇦🇪', phoneCode: '971', currency: 'AED', locale: 'ar_AE' },
            'RU': { name: 'Russia', flag: '🇷🇺', phoneCode: '7', currency: 'RUB', locale: 'ru_RU' }
        };
        
        // First names by region
        const firstNames = {
            western: ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth', 'David', 'Susan', 'Richard', 'Jessica', 'Joseph', 'Sarah'],
            african: ['Baraka', 'Imani', 'Jabari', 'Zuri', 'Kwame', 'Amina', 'Kamau', 'Fatima', 'Oluwaseun', 'Chiamaka', 'Sipho', 'Thandi', 'Mohammed', 'Aisha', 'Musa', 'Ngozi'],
            asian: ['Chen', 'Li', 'Wang', 'Zhang', 'Liu', 'Sato', 'Tanaka', 'Kim', 'Park', 'Lee', 'Raj', 'Priya', 'Arun', 'Meera', 'Yuki', 'Haruki'],
            arabic: ['Mohammed', 'Ahmed', 'Ali', 'Fatima', 'Aisha', 'Omar', 'Layla', 'Hassan', 'Zainab', 'Abdullah', 'Noor', 'Ibrahim', 'Amira', 'Yusuf', 'Mariam', 'Khaled']
        };
        
        // Last names by region
        const lastNames = {
            western: ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas'],
            african: ['Okafor', 'Mensah', 'Nkosi', 'Dlamini', 'Abebe', 'Bekele', 'Kamau', 'Mwangi', 'Okonkwo', 'Adeyemi', 'Van der Merwe', 'Botha', 'Mohamed', 'Hassan', 'Ibrahim', 'Ali'],
            asian: ['Wang', 'Li', 'Zhang', 'Liu', 'Chen', 'Yang', 'Huang', 'Zhao', 'Wu', 'Zhou', 'Suzuki', 'Tanaka', 'Kim', 'Park', 'Lee', 'Choi'],
            arabic: ['Al-Saud', 'Al-Thani', 'Al-Nahyan', 'Al-Sabah', 'Al-Said', 'Al-Hashemi', 'Al-Husseini', 'Al-Rashid', 'Al-Sharif', 'Al-Atrash', 'Al-Qahtani', 'Al-Ghamdi', 'Al-Shammari', 'Al-Mutairi', 'Al-Dosari', 'Al-Otaibi']
        };
        
        // Cities by country
        const cities = {
            'US': ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'Austin', 'Boston', 'Seattle', 'Denver', 'Miami', 'Atlanta'],
            'GB': ['London', 'Manchester', 'Birmingham', 'Glasgow', 'Liverpool', 'Edinburgh', 'Bristol', 'Leeds', 'Sheffield', 'Newcastle', 'Cardiff', 'Belfast', 'Nottingham', 'Leicester', 'Coventry'],
            'CA': ['Toronto', 'Vancouver', 'Montreal', 'Calgary', 'Edmonton', 'Ottawa', 'Winnipeg', 'Quebec', 'Hamilton', 'Halifax', 'Victoria', 'Saskatoon', 'Regina', 'St. John\'s', 'London'],
            'AU': ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide', 'Gold Coast', 'Canberra', 'Newcastle', 'Wollongong', 'Hobart', 'Darwin', 'Geelong', 'Townsville', 'Cairns', 'Toowoomba'],
            'KE': ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika', 'Malindi', 'Kitale', 'Garissa', 'Nyeri', 'Machakos', 'Meru', 'Kakamega', 'Nanyuki', 'Naivasha'],
            'NG': ['Lagos', 'Kano', 'Ibadan', 'Abuja', 'Port Harcourt', 'Benin City', 'Kaduna', 'Zaria', 'Maiduguri', 'Jos', 'Enugu', 'Abeokuta', 'Ilorin', 'Warri', 'Owerri'],
            'ZA': ['Johannesburg', 'Cape Town', 'Durban', 'Pretoria', 'Port Elizabeth', 'Bloemfontein', 'Nelspruit', 'Kimberley', 'Polokwane', 'Pietermaritzburg', 'East London', 'George', 'Rustenburg', 'Stellenbosch', 'Paarl'],
            'IN': ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Lucknow', 'Kanpur', 'Nagpur', 'Indore', 'Thane', 'Bhopal']
        };
        
        // Streets by region
        const streets = {
            western: ['Main St', 'Oak Ave', 'Maple Rd', 'Pine St', 'Cedar Ln', 'Elm St', 'Washington Ave', 'Lake Dr', 'Hill St', 'Park Ave', 'Church St', 'River Rd', 'Forest Ave', 'Spring St', 'View Dr'],
            african: ['Kenyatta Ave', 'Uhuru Highway', 'Moi Ave', 'Jomo Kenyatta Rd', 'Independence Ave', 'Nyerere Rd', 'Mandela St', 'Lumumba Ave', 'Sankara Rd', 'Azikiwe St', 'Obasanjo Way', 'Kaunda Rd', 'Mugabe Ave', 'Gandhi St', 'Martin Luther King Rd'],
            asian: ['Sakura St', 'Main Street', 'Central Ave', 'East Rd', 'West Blvd', 'North Lane', 'South Ave', 'Garden Rd', 'River St', 'Mountain View', 'Sunset Blvd', 'Cherry Blossom Ln', 'Dragon St', 'Pearl Rd', 'Jade Ave'],
            arabic: ['Al Falah St', 'Al Maktoum Rd', 'King Fahd Rd', 'Sheikh Zayed Rd', 'Al Nahyan St', 'Al Karama St', 'Al Rigga St', 'Al Muraqqabat St', 'Al Khaleej St', 'Al Wasl St', 'Al Saffa St', 'Al Nahda St', 'Al Quds St', 'Al Haram St', 'Al Medina St']
        };
        
        function getRegionForCountry(country) {
            const africanCountries = ['KE', 'NG', 'ZA', 'EG', 'MA', 'TZ', 'UG', 'GH', 'SN', 'CI'];
            const asianCountries = ['JP', 'CN', 'IN', 'KR', 'SG', 'MY', 'TH', 'VN', 'PH', 'PK'];
            const arabicCountries = ['AE', 'SA', 'QA', 'KW', 'OM', 'BH', 'JO', 'LB', 'SY', 'IQ'];
            
            if (africanCountries.includes(country)) return 'african';
            if (asianCountries.includes(country)) return 'asian';
            if (arabicCountries.includes(country)) return 'arabic';
            return 'western';
        }
        
        function getRandomItem(arr) {
            return arr[Math.floor(Math.random() * arr.length)];
        }
        
        function generatePhone(country) {
            const countryInfo = countryData[country];
            const area = Math.floor(Math.random() * 900) + 100;
            const mid = Math.floor(Math.random() * 900) + 100;
            const last = Math.floor(Math.random() * 9000) + 1000;
            
            if (country === 'US' || country === 'CA') {
                return `+${countryInfo.phoneCode} (${area}) ${mid}-${last}`;
            } else if (country === 'GB') {
                return `+${countryInfo.phoneCode} ${area} ${mid} ${last}`;
            } else if (country === 'KE') {
                const prefixes = ['700', '701', '702', '703', '704', '705', '706', '707', '708', '709', '710', '711', '712', '713', '714', '715', '716', '717', '718', '719', '720', '721', '722', '723', '724', '725', '726', '727', '728', '729', '730', '731', '732', '733', '734', '735', '736', '737', '738', '739', '740', '741', '742', '743', '744', '745', '746', '747', '748', '749', '750', '751', '752', '753', '754', '755', '756', '757', '758', '759'];
                const prefix = getRandomItem(prefixes);
                return `+${countryInfo.phoneCode} ${prefix} ${Math.floor(Math.random() * 100000)}`;
            } else {
                return `+${countryInfo.phoneCode} ${area} ${mid} ${last}`;
            }
        }
        
        function generateDOB() {
            const year = Math.floor(Math.random() * 40) + 1960;
            const month = Math.floor(Math.random() * 12) + 1;
            const day = Math.floor(Math.random() * 28) + 1;
            return `${month.toString().padStart(2, '0')}/${day.toString().padStart(2, '0')}/${year}`;
        }
        
        function generateEmail(firstName, lastName, country) {
            const domains = {
                'US': ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com'],
                'GB': ['gmail.com', 'yahoo.co.uk', 'hotmail.co.uk', 'outlook.com'],
                'KE': ['gmail.com', 'yahoo.com', 'outlook.com', 'safaricom.co.ke'],
                'NG': ['gmail.com', 'yahoo.com', 'outlook.com', 'naijamail.com'],
                'ZA': ['gmail.com', 'yahoo.com', 'outlook.com', 'webmail.co.za']
            };
            const domainList = domains[country] || ['gmail.com', 'yahoo.com', 'outlook.com'];
            const emailName = `${firstName.toLowerCase()}.${lastName.toLowerCase()}${Math.floor(Math.random() * 1000)}`;
            return `${emailName}@${getRandomItem(domainList)}`;
        }
        
        function generateAddress(country, index) {
            const region = getRegionForCountry(country);
            const firstName = getRandomItem(firstNames[region]);
            const lastName = getRandomItem(lastNames[region]);
            const fullName = `${firstName} ${lastName}`;
            const streetNum = Math.floor(Math.random() * 9999) + 1;
            const street = getRandomItem(streets[region]);
            const cityList = cities[country] || cities['US'];
            const city = getRandomItem(cityList);
            const zip = Math.floor(Math.random() * 90000) + 10000;
            const phone = generatePhone(country);
            const dob = generateDOB();
            const email = generateEmail(firstName, lastName, country);
            const countryInfo = countryData[country];
            
            const fullAddress = `${fullName}\n${streetNum} ${street}\n${city}, ${zip}\n${countryInfo.name}`;
            
            return {
                id: index,
                name: fullName,
                firstName: firstName,
                lastName: lastName,
                email: email,
                street: `${streetNum} ${street}`,
                city: city,
                zip: zip,
                country: country,
                countryName: countryInfo.name,
                countryFlag: countryInfo.flag,
                phone: phone,
                dob: dob,
                avatar: firstName.charAt(0) + lastName.charAt(0),
                fullAddress: fullAddress
            };
        }
        
        function generateAddresses() {
            const country = $('#country').val();
            const quantity = parseInt($('#quantity').val());
            const newAddresses = [];
            
            for (let i = 0; i < quantity; i++) {
                newAddresses.push(generateAddress(country, i));
            }
            
            generatedAddresses = newAddresses;
            updateStats();
            renderResults();
            if (generatedAddresses.length > 0) updatePreview(generatedAddresses[0]);
            
            Swal.fire({
                title: 'Generated!',
                text: `${quantity} addresses generated`,
                icon: 'success',
                toast: true,
                timer: 1500,
                showConfirmButton: false
            });
        }
        
        function updateStats() {
            $('#genCount').text(generatedAddresses.length);
            $('#resultCount').text(generatedAddresses.length);
            $('#quality').text(generatedAddresses.length > 0 ? 'High' : '-');
        }
        
        function updatePreview(address) {
            if (!address) return;
            
            const html = `
                <div class="profile-card">
                    <div class="profile-avatar">${address.avatar}</div>
                    <div class="profile-name">${address.name}</div>
                    <div class="profile-email">${address.email}</div>
                </div>
                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Phone</div><div class="info-value">${address.phone}</div></div>
                    <div class="info-item"><div class="info-label">DOB</div><div class="info-value">${address.dob}</div></div>
                </div>
                <div class="address-box">
                    <div class="address-text">${address.fullAddress.replace(/\n/g, '<br>')}</div>
                </div>
            `;
            $('#previewContent').html(html);
        }
        
        function renderResults() {
            if (generatedAddresses.length === 0) {
                $('#resultsContainer').html('<div class="result-item header"><div></div><div>Name / Address</div><div>Action</div></div><div class="result-item"><div colspan="3" style="text-align: center; color: var(--text-muted); padding: 1rem;">No addresses generated</div></div>');
                return;
            }
            
            let html = '<div class="result-item header"><div></div><div>Name / Address</div><div>Action</div></div>';
            
            generatedAddresses.forEach((addr, idx) => {
                html += `
                    <div class="result-item">
                        <div class="result-avatar">${addr.avatar}</div>
                        <div>
                            <div><strong>${addr.name}</strong> ${addr.countryFlag}</div>
                            <div style="font-size: 0.55rem; color: var(--text-muted);">${addr.street}, ${addr.city}</div>
                            <div style="font-size: 0.5rem; color: var(--text-muted);">${addr.email} | ${addr.phone}</div>
                        </div>
                        <div>
                            <button class="btn btn-sm copy-addr" data-idx="${idx}"><i class="fas fa-copy"></i> Copy</button>
                        </div>
                    </div>
                `;
            });
            
            $('#resultsContainer').html(html);
            
            $('.copy-addr').click(function() {
                const idx = $(this).data('idx');
                copyAddress(idx);
            });
        }
        
        function copyAddress(index) {
            const addr = generatedAddresses[index];
            const text = `${addr.name}\n${addr.street}\n${addr.city}, ${addr.zip}\n${addr.countryName}\nPhone: ${addr.phone}\nEmail: ${addr.email}\nDOB: ${addr.dob}`;
            navigator.clipboard.writeText(text).then(() => {
                Swal.fire({
                    title: 'Copied!',
                    text: 'Address copied to clipboard',
                    icon: 'success',
                    toast: true,
                    timer: 1000,
                    showConfirmButton: false,
                    position: 'top-end'
                });
            });
        }
        
        function copyAll() {
            if (generatedAddresses.length === 0) {
                Swal.fire('No addresses', 'Generate addresses first', 'warning');
                return;
            }
            
            let text = '';
            generatedAddresses.forEach(addr => {
                text += `${addr.name}\n${addr.street}\n${addr.city}, ${addr.zip}\n${addr.countryName}\nPhone: ${addr.phone}\nEmail: ${addr.email}\n---\n`;
            });
            navigator.clipboard.writeText(text);
            Swal.fire('Copied!', `${generatedAddresses.length} addresses copied`, 'success');
        }
        
        $('#generateBtn').click(generateAddresses);
        $('#clearBtn').click(() => {
            generatedAddresses = [];
            updateStats();
            renderResults();
            $('#previewContent').html(`
                <div class="profile-card">
                    <div class="profile-avatar"><i class="fas fa-user"></i></div>
                    <div class="profile-name">John Doe</div>
                    <div class="profile-email">john.doe@example.com</div>
                </div>
                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Phone</div><div class="info-value">+1 (555) 123-4567</div></div>
                    <div class="info-item"><div class="info-label">DOB</div><div class="info-value">01/15/1990</div></div>
                </div>
                <div class="address-box">
                    <div class="address-text">123 Main Street<br>Apt 4B<br>New York, NY 10001<br>USA</div>
                </div>
            `);
        });
        $('#copyAllBtn').click(copyAll);
        
        // Theme handling
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
        const themeBtn = document.getElementById('themeBtn');
        if(themeBtn){
            themeBtn.innerHTML = savedTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            themeBtn.addEventListener('click', () => {
                const newTheme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.body.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                themeBtn.innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
            });
        }
        
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const main = document.getElementById('main');
        if(menuBtn) menuBtn.addEventListener('click', () => { sidebar.classList.toggle('open'); main.classList.toggle('sidebar-open'); });
        
        // Generate default on load
        generateAddresses();
    </script>
</body>
</html>
