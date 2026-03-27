<?php
require_once __DIR__ . "/../../includes/config.php";
if (!isLoggedIn()) { 
    header('Location: /login.php'); 
    exit; 
}

$pageTitle = "CC Generator";
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
        .section-title { font-size: 0.8rem; font-weight: 600; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .input-group { margin-bottom: 0.8rem; }
        .input-group label { display: block; font-size: 0.6rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.2rem; font-weight: 600; }
        .input-group input, .input-group select { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.4rem 0.6rem; color: var(--text); font-size: 0.7rem; font-family: monospace; }
        
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.7rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.6rem; }
        
        .results-table { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; overflow: hidden; }
        .results-header { padding: 0.5rem 0.8rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .results-header h3 { font-size: 0.7rem; font-weight: 600; }
        .result-item { padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border); display: grid; grid-template-columns: 160px 70px 60px 80px 1fr 70px; gap: 0.5rem; font-size: 0.65rem; align-items: center; }
        .result-item.header { background: var(--bg); font-weight: 600; color: var(--text-muted); }
        
        .badge { display: inline-block; padding: 0.1rem 0.3rem; border-radius: 0.2rem; font-size: 0.55rem; font-weight: 600; }
        .badge-visa { background: #1a1f71; color: white; }
        .badge-mastercard { background: #eb001b; color: white; }
        .badge-amex { background: #006fcf; color: white; }
        .badge-discover { background: #ff6000; color: white; }
        
        .stats { display: flex; gap: 0.5rem; margin-bottom: 0.8rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.3rem 0.6rem; text-align: center; }
        .stat-number { font-size: 0.9rem; font-weight: 700; }
        .stat-label { font-size: 0.55rem; color: var(--text-muted); }
        
        .layout-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 768px) { .layout-2col { grid-template-columns: 1fr; } }
        
        .cc-preview { font-family: monospace; font-size: 0.8rem; background: var(--bg); padding: 0.5rem; border-radius: 0.4rem; text-align: center; }
        .copy-icon { cursor: pointer; color: var(--text-muted); transition: color 0.2s; margin-left: 0.3rem; font-size: 0.6rem; }
        .copy-icon:hover { color: var(--primary); }
        .copy-card-btn { background: var(--primary); border: none; color: white; padding: 0.2rem 0.5rem; border-radius: 0.3rem; font-size: 0.55rem; cursor: pointer; transition: all 0.2s; }
        .copy-card-btn:hover { background: #7c3aed; transform: scale(1.02); }
        
        .full-card-code { font-family: monospace; font-size: 0.65rem; background: var(--bg); padding: 0.2rem 0.3rem; border-radius: 0.2rem; display: inline-block; max-width: 180px; overflow-x: auto; }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">CC Generator</h1>
                <p class="page-subtitle">Generate valid credit cards with Luhn algorithm</p>
            </div>
            
            <div class="stats">
                <div class="stat-card"><div class="stat-number" id="genCount">0</div><div class="stat-label">Generated</div></div>
                <div class="stat-card"><div class="stat-number" id="validCount">0</div><div class="stat-label">Valid</div></div>
            </div>
            
            <div class="layout-2col">
                <div class="generator-section">
                    <div class="section-title"><i class="fas fa-cog"></i> Generator Settings</div>
                    
                    <div class="input-group">
                        <label>Card Brand</label>
                        <select id="brand">
                            <option value="visa">Visa</option>
                            <option value="mastercard">Mastercard</option>
                            <option value="amex">American Express</option>
                            <option value="discover">Discover</option>
                            <option value="random">Random</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>Custom BIN (first 6 digits)</label>
                        <input type="text" id="customBin" placeholder="Leave empty for random" maxlength="6">
                    </div>
                    
                    <div class="input-group">
                        <label>Quantity</label>
                        <select id="quantity">
                            <option value="1">1</option>
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>Expiry Format</label>
                        <select id="expiryFormat">
                            <option value="mm|yy">MM|YY (for checkers)</option>
                            <option value="mm/yy">MM/YY</option>
                            <option value="mm-yy">MM-YY</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                        <button class="btn btn-primary" id="generateBtn"><i class="fas fa-random"></i> Generate</button>
                        <button class="btn btn-secondary" id="clearBtn"><i class="fas fa-trash"></i> Clear</button>
                        <button class="btn btn-success" id="copyAllBtn"><i class="fas fa-copy"></i> Copy All</button>
                    </div>
                </div>
                
                <div class="generator-section">
                    <div class="section-title"><i class="fas fa-info-circle"></i> BIN Information</div>
                    <div id="binInfo" class="cc-preview" style="text-align: left; font-size: 0.7rem;">
                        <div style="color: var(--text-muted);">Select a brand or enter custom BIN</div>
                    </div>
                </div>
            </div>
            
            <div class="results-table">
                <div class="results-header">
                    <h3><i class="fas fa-list"></i> Generated Cards (<span id="resultCount">0</span>)</h3>
                    <div>
                        <button class="btn btn-sm filter-btn active" data-filter="all">All</button>
                        <button class="btn btn-sm filter-btn" data-filter="valid">Valid</button>
                    </div>
                </div>
                <div id="resultsContainer">
                    <div class="result-item header">
                        <div>Card Number</div>
                        <div>Expiry</div>
                        <div>CVV</div>
                        <div>Brand</div>
                        <div>Full Card</div>
                        <div>Action</div>
                    </div>
                    <div class="result-item"><div colspan="6" style="text-align: center; color: var(--text-muted); padding: 1rem;">Click Generate to create cards</div></div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        let generatedCards = [];
        let currentFilter = 'all';
        
        const binPrefixes = {
            visa: ['4'],
            mastercard: ['51', '52', '53', '54', '55', '2221', '2222', '2223', '2224', '2225', '2226', '2227', '2228', '2229', '223', '224', '225', '226', '227', '228', '229', '23', '24', '25', '26', '270', '271', '2720'],
            amex: ['34', '37'],
            discover: ['6011', '65', '644', '645', '646', '647', '648', '649']
        };
        
        const bankInfo = {
            '4': { bank: 'Visa Inc.', country: 'USA', type: 'Credit' },
            '51': { bank: 'Mastercard', country: 'USA', type: 'Credit' },
            '52': { bank: 'Mastercard', country: 'USA', type: 'Credit' },
            '53': { bank: 'Mastercard', country: 'USA', type: 'Credit' },
            '54': { bank: 'Mastercard', country: 'USA', type: 'Credit' },
            '55': { bank: 'Mastercard', country: 'USA', type: 'Credit' },
            '34': { bank: 'American Express', country: 'USA', type: 'Credit' },
            '37': { bank: 'American Express', country: 'USA', type: 'Credit' },
            '6011': { bank: 'Discover Bank', country: 'USA', type: 'Credit' },
            '65': { bank: 'Discover Bank', country: 'USA', type: 'Credit' }
        };
        
        function copyToClipboard(text, label) {
            navigator.clipboard.writeText(text).then(function() {
                Swal.fire({
                    title: 'Copied!',
                    text: label || text,
                    icon: 'success',
                    toast: true,
                    timer: 1200,
                    showConfirmButton: false,
                    position: 'top-end'
                });
            }).catch(function() {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                Swal.fire({
                    title: 'Copied!',
                    text: label || text,
                    icon: 'success',
                    toast: true,
                    timer: 1200,
                    showConfirmButton: false,
                    position: 'top-end'
                });
            });
        }
        
        function luhnCheckDigit(partial) {
            let sum = 0;
            let alternate = false;
            for (let i = partial.length - 1; i >= 0; i--) {
                let n = parseInt(partial[i], 10);
                if (alternate) {
                    n *= 2;
                    if (n > 9) n -= 9;
                }
                sum += n;
                alternate = !alternate;
            }
            return (sum * 9) % 10;
        }
        
        function generateCardNumber(bin, length = 16) {
            if (!bin) return null;
            let partial = bin;
            while (partial.length < length - 1) {
                partial += Math.floor(Math.random() * 10);
            }
            return partial + luhnCheckDigit(partial);
        }
        
        function generateExpiry() {
            const now = new Date();
            const currentYear = now.getFullYear();
            const month = String(Math.floor(Math.random() * 12) + 1).padStart(2, '0');
            const year = String(currentYear + Math.floor(Math.random() * 5) + 1).slice(-2);
            return { month, year };
        }
        
        function generateCVV(brand) {
            const length = brand === 'amex' ? 4 : 3;
            return String(Math.floor(Math.random() * Math.pow(10, length))).padStart(length, '0');
        }
        
        function getBinForBrand(brand, customBin) {
            if (customBin && customBin.length >= 6) return customBin;
            const prefixes = binPrefixes[brand];
            if (!prefixes) {
                const allBins = [...binPrefixes.visa, ...binPrefixes.mastercard, ...binPrefixes.amex, ...binPrefixes.discover];
                return allBins[Math.floor(Math.random() * allBins.length)];
            }
            return prefixes[Math.floor(Math.random() * prefixes.length)];
        }
        
        function getCardBrand(bin) {
            const binStr = bin.toString();
            if (binStr.startsWith('4')) return 'Visa';
            if (/^5[1-5]/.test(binStr) || /^2[2-7]/.test(binStr)) return 'Mastercard';
            if (/^3[47]/.test(binStr)) return 'Amex';
            if (/^6(011|5|4[4-9]|5[0-9])/.test(binStr)) return 'Discover';
            return 'Unknown';
        }
        
        function getBankInfo(bin) {
            const binStr = bin.toString();
            for (const [prefix, info] of Object.entries(bankInfo)) {
                if (binStr.startsWith(prefix)) return info;
            }
            return { bank: 'Unknown', country: 'Unknown', type: 'Unknown' };
        }
        
        function formatExpiry(month, year, format) {
            switch(format) {
                case 'mm/yy': return `${month}/${year}`;
                case 'mm-yy': return `${month}-${year}`;
                default: return `${month}|${year}`;
            }
        }
        
        function generateCards() {
            const brand = $('#brand').val();
            const customBin = $('#customBin').val().trim();
            const quantity = parseInt($('#quantity').val());
            const expiryFormat = $('#expiryFormat').val();
            
            const newCards = [];
            
            for (let i = 0; i < quantity; i++) {
                const bin = getBinForBrand(brand, customBin);
                const cardLength = brand === 'amex' ? 15 : 16;
                const cardNumber = generateCardNumber(bin, cardLength);
                const { month, year } = generateExpiry();
                const expiry = formatExpiry(month, year, expiryFormat);
                const cvv = generateCVV(brand);
                const cardBrand = getCardBrand(cardNumber);
                const bank = getBankInfo(cardNumber);
                
                newCards.push({
                    number: cardNumber,
                    month: month,
                    year: year,
                    expiry: expiry,
                    cvv: cvv,
                    brand: cardBrand,
                    bank: bank.bank,
                    fullCard: `${cardNumber}|${month}|${year}|${cvv}`
                });
            }
            
            generatedCards = newCards;
            updateStats();
            renderResults();
        }
        
        function updateStats() {
            $('#genCount').text(generatedCards.length);
            $('#validCount').text(generatedCards.length);
        }
        
        function renderResults() {
            let filtered = generatedCards;
            if (currentFilter === 'valid') filtered = generatedCards;
            
            $('#resultCount').text(filtered.length);
            
            if (filtered.length === 0) {
                $('#resultsContainer').html('<div class="result-item header"><div>Card Number</div><div>Expiry</div><div>CVV</div><div>Brand</div><div>Full Card</div><div>Action</div></div><div class="result-item"><div colspan="6" style="text-align: center; color: var(--text-muted); padding: 1rem;">No cards generated</div></div>');
                return;
            }
            
            let html = '<div class="result-item header"><div>Card Number</div><div>Expiry</div><div>CVV</div><div>Brand</div><div>Full Card</div><div>Action</div></div>';
            
            filtered.forEach((card, idx) => {
                const badgeClass = card.brand.toLowerCase();
                html += `
                    <div class="result-item" data-card-idx="${idx}">
                        <div>
                            <code>${card.number}</code>
                            <i class="fas fa-copy copy-icon" onclick="copyToClipboard('${card.number}', 'Card number copied')"></i>
                        </div>
                        <div>
                            ${card.expiry}
                            <i class="fas fa-copy copy-icon" onclick="copyToClipboard('${card.expiry}', 'Expiry copied')"></i>
                        </div>
                        <div>
                            ${card.cvv}
                            <i class="fas fa-copy copy-icon" onclick="copyToClipboard('${card.cvv}', 'CVV copied')"></i>
                        </div>
                        <div><span class="badge badge-${badgeClass}">${card.brand}</span></div>
                        <div>
                            <code class="full-card-code">${card.fullCard}</code>
                            <i class="fas fa-copy copy-icon" onclick="copyToClipboard('${card.fullCard}', 'Full card copied')"></i>
                        </div>
                        <div>
                            <button class="copy-card-btn" onclick="copyToClipboard('${card.fullCard}', 'Full card copied')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                `;
            });
            
            $('#resultsContainer').html(html);
        }
        
        function copyAllCards() {
            if (generatedCards.length === 0) {
                Swal.fire('No cards', 'Generate cards first', 'warning');
                return;
            }
            
            let text = '';
            generatedCards.forEach(card => {
                text += `${card.fullCard}\n`;
            });
            copyToClipboard(text, `${generatedCards.length} cards copied`);
        }
        
        function updateBinInfo() {
            const brand = $('#brand').val();
            const customBin = $('#customBin').val().trim();
            let bin = customBin;
            
            if (!bin) {
                const prefixes = binPrefixes[brand];
                if (prefixes && prefixes.length) bin = prefixes[0] + 'xxxxx';
                else bin = '4xxxxx';
            }
            
            const bank = getBankInfo(bin);
            const cardBrand = getCardBrand(bin);
            
            let html = `
                <div style="margin-bottom: 0.3rem;"><strong>BIN:</strong> ${bin}</div>
                <div style="margin-bottom: 0.3rem;"><strong>Brand:</strong> ${cardBrand}</div>
                <div style="margin-bottom: 0.3rem;"><strong>Bank:</strong> ${bank.bank}</div>
                <div style="margin-bottom: 0.3rem;"><strong>Country:</strong> ${bank.country}</div>
                <div><strong>Type:</strong> ${bank.type}</div>
            `;
            $('#binInfo').html(html);
        }
        
        $('#generateBtn').click(generateCards);
        $('#clearBtn').click(() => {
            generatedCards = [];
            renderResults();
            updateStats();
        });
        $('#copyAllBtn').click(copyAllCards);
        $('#brand, #customBin').on('input', updateBinInfo);
        
        $('.filter-btn').click(function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            renderResults();
        });
        
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
        
        updateBinInfo();
    </script>
</body>
</html>
