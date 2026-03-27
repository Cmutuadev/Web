<?php
require_once __DIR__ . "/../../includes/config.php";
if (!isLoggedIn()) { 
    header('Location: /login.php'); 
    exit; 
}

$pageTitle = "CC Cleaner";
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
        
        .cleaner-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; }
        .section-title { font-size: 0.8rem; font-weight: 600; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .input-group { margin-bottom: 0.8rem; }
        .input-group label { display: block; font-size: 0.6rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.2rem; font-weight: 600; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.4rem 0.6rem; color: var(--text); font-size: 0.7rem; font-family: monospace; }
        
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.7rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-warning { background: linear-gradient(135deg, var(--warning), #d97706); color: white; }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.6rem; }
        
        .results-table { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; overflow: hidden; }
        .results-header { padding: 0.5rem 0.8rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .results-header h3 { font-size: 0.7rem; font-weight: 600; }
        .result-item { padding: 0.5rem 0.8rem; border-bottom: 1px solid var(--border); display: grid; grid-template-columns: 200px 100px 80px 80px 80px 1fr; gap: 0.5rem; font-size: 0.65rem; align-items: center; }
        .result-item.header { background: var(--bg); font-weight: 600; color: var(--text-muted); }
        
        .badge { display: inline-block; padding: 0.1rem 0.3rem; border-radius: 0.2rem; font-size: 0.55rem; font-weight: 600; }
        .badge-success { background: var(--success); color: white; }
        .badge-danger { background: var(--danger); color: white; }
        .badge-warning { background: var(--warning); color: white; }
        
        .stats { display: flex; gap: 0.5rem; margin-bottom: 0.8rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.3rem 0.6rem; text-align: center; flex: 1; }
        .stat-number { font-size: 0.9rem; font-weight: 700; }
        .stat-label { font-size: 0.55rem; color: var(--text-muted); }
        
        .layout-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 768px) { .layout-2col { grid-template-columns: 1fr; } }
        
        .copy-icon { cursor: pointer; color: var(--text-muted); margin-left: 0.3rem; font-size: 0.55rem; }
        .copy-icon:hover { color: var(--primary); }
        
        .progress-bar { height: 2px; background: var(--border); margin-top: 0.5rem; border-radius: 2px; overflow: hidden; display: none; }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.3s; }
        
        .checkbox-group { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; }
        .checkbox-group input { width: auto; margin-right: 0.2rem; }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">CC Cleaner</h1>
                <p class="page-subtitle">Clean, format, split, and validate credit cards</p>
            </div>
            
            <div class="stats">
                <div class="stat-card"><div class="stat-number" id="totalCount">0</div><div class="stat-label">Total</div></div>
                <div class="stat-card"><div class="stat-number" id="validCount">0</div><div class="stat-label">Valid</div></div>
                <div class="stat-card"><div class="stat-number" id="invalidCount">0</div><div class="stat-label">Invalid</div></div>
                <div class="stat-card"><div class="stat-number" id="duplicateCount">0</div><div class="stat-label">Duplicates</div></div>
            </div>
            
            <div class="layout-2col">
                <div class="cleaner-section">
                    <div class="section-title"><i class="fas fa-edit"></i> Input Cards</div>
                    <div class="input-group">
                        <label>Paste Cards (one per line)</label>
                        <textarea id="cardInput" rows="8" placeholder="5509890034877216|06|2028|333&#10;4111111111111111|12|2025|123&#10;4242424242424242|08|2027|555"></textarea>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button class="btn btn-primary" id="cleanBtn"><i class="fas fa-broom"></i> Clean Cards</button>
                        <button class="btn btn-secondary" id="importBtn"><i class="fas fa-upload"></i> Import</button>
                        <button class="btn btn-secondary" id="exportBtn"><i class="fas fa-download"></i> Export</button>
                        <input type="file" id="fileInput" style="display: none;" accept=".txt,.csv">
                    </div>
                    <div class="progress-bar" id="progressBar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                </div>
                
                <div class="cleaner-section">
                    <div class="section-title"><i class="fas fa-sliders-h"></i> Cleaner Settings</div>
                    
                    <div class="input-group">
                        <label>Split Cards (split into parts)</label>
                        <input type="text" id="splitCount" placeholder="Number of parts (e.g., 2, 3, 5)">
                        <button class="btn btn-sm btn-secondary" id="splitBtn" style="margin-top: 0.3rem;"><i class="fas fa-cut"></i> Split Cards</button>
                    </div>
                    
                    <div class="input-group">
                        <label>Check with Gate (optional)</label>
                        <select id="gateSelect">
                            <option value="">-- Select Gate (Optional) --</option>
                            <option value="key_stripe.php">Stripe Key Checker</option>
                            <option value="paypal-key.php">PayPal Key Checker</option>
                            <option value="shopify.php">Shopify Checker</option>
                            <option value="stripe-invoice.php">Stripe Invoice</option>
                            <option value="stripe_auth.php">Stripe Auth</option>
                        </select>
                        <div class="checkbox-group" style="margin-top: 0.3rem;">
                            <input type="checkbox" id="checkWithGate">
                            <label>Check cleaned cards with selected gate</label>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label>Options</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="removeDuplicates" checked>
                            <label>Remove duplicates</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="validateLuhn" checked>
                            <label>Validate with Luhn algorithm</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="sortByBrand">
                            <label>Sort by card brand</label>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                        <button class="btn btn-success" id="downloadCleanBtn"><i class="fas fa-download"></i> Download Cleaned</button>
                        <button class="btn btn-warning" id="saveToStorageBtn"><i class="fas fa-database"></i> Save to Storage</button>
                        <button class="btn btn-info" id="loadFromStorageBtn"><i class="fas fa-folder-open"></i> Load from Storage</button>
                    </div>
                </div>
            </div>
            
            <div class="results-table">
                <div class="results-header">
                    <h3><i class="fas fa-list"></i> Cleaned Cards (<span id="resultCount">0</span>)</h3>
                    <div>
                        <button class="btn btn-sm filter-btn active" data-filter="all">All</button>
                        <button class="btn btn-sm filter-btn" data-filter="valid">Valid</button>
                        <button class="btn btn-sm filter-btn" data-filter="invalid">Invalid</button>
                    </div>
                </div>
                <div id="resultsContainer">
                    <div class="result-item header">
                        <div>Card Number</div>
                        <div>Expiry</div>
                        <div>CVV</div>
                        <div>Brand</div>
                        <div>Status</div>
                        <div>Action</div>
                    </div>
                    <div class="result-item"><div colspan="6" style="text-align: center; color: var(--text-muted); padding: 1rem;">Paste cards and click Clean</div></div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        let cleanedCards = [];
        let currentFilter = 'all';
        
        // Luhn algorithm validation
        function luhnCheck(cardNumber) {
            let sum = 0;
            let alternate = false;
            for (let i = cardNumber.length - 1; i >= 0; i--) {
                let n = parseInt(cardNumber[i], 10);
                if (alternate) {
                    n *= 2;
                    if (n > 9) n -= 9;
                }
                sum += n;
                alternate = !alternate;
            }
            return sum % 10 === 0;
        }
        
        function getCardBrand(cardNumber) {
            const bin = cardNumber.substring(0, 6);
            if (bin.startsWith('4')) return 'Visa';
            if (/^5[1-5]/.test(bin) || /^2[2-7]/.test(bin)) return 'Mastercard';
            if (/^3[47]/.test(bin)) return 'Amex';
            if (/^6(011|5|4[4-9]|5[0-9])/.test(bin)) return 'Discover';
            return 'Unknown';
        }
        
        function parseCard(line) {
            line = line.trim();
            if (!line) return null;
            
            let cardNumber = '', month = '', year = '', cvv = '';
            
            // Format: number|month|year|cvv
            if (line.includes('|')) {
                const parts = line.split('|');
                if (parts.length >= 4) {
                    cardNumber = parts[0].replace(/\D/g, '');
                    month = parts[1].replace(/\D/g, '').padStart(2, '0');
                    year = parts[2].replace(/\D/g, '');
                    if (year.length === 2) year = '20' + year;
                    cvv = parts[3].replace(/\D/g, '');
                }
            }
            // Format: number month year cvv (space separated)
            else if (line.includes(' ')) {
                const parts = line.split(/\s+/);
                if (parts.length >= 4) {
                    cardNumber = parts[0].replace(/\D/g, '');
                    month = parts[1].replace(/\D/g, '').padStart(2, '0');
                    year = parts[2].replace(/\D/g, '');
                    if (year.length === 2) year = '20' + year;
                    cvv = parts[3].replace(/\D/g, '');
                }
            }
            
            if (!cardNumber || !month || !year || !cvv) return null;
            
            const isValid = luhnCheck(cardNumber);
            const brand = getCardBrand(cardNumber);
            
            return {
                number: cardNumber,
                month: month,
                year: year,
                cvv: cvv,
                expiry: `${month}|${year.slice(-2)}`,
                brand: brand,
                valid: isValid,
                original: `${cardNumber}|${month}|${year.slice(-2)}|${cvv}`
            };
        }
        
        function cleanCards() {
            const input = $('#cardInput').val();
            const lines = input.split('\n');
            let cards = [];
            let duplicates = new Set();
            
            lines.forEach(line => {
                const card = parseCard(line);
                if (card) {
                    const key = `${card.number}|${card.month}|${card.year}|${card.cvv}`;
                    if ($('#removeDuplicates').is(':checked') && duplicates.has(key)) {
                        card.duplicate = true;
                    } else {
                        duplicates.add(key);
                        cards.push(card);
                    }
                }
            });
            
            if ($('#validateLuhn').is(':checked')) {
                cards = cards.filter(c => c.valid);
            }
            
            if ($('#sortByBrand').is(':checked')) {
                const brandOrder = { 'Visa': 1, 'Mastercard': 2, 'Amex': 3, 'Discover': 4, 'Unknown': 5 };
                cards.sort((a, b) => (brandOrder[a.brand] || 99) - (brandOrder[b.brand] || 99));
            }
            
            cleanedCards = cards;
            updateStats();
            renderResults();
            
            if (cards.length > 0) {
                Swal.fire('Success', `Cleaned ${cards.length} cards`, 'success');
            } else {
                Swal.fire('No valid cards', 'No valid cards found after cleaning', 'warning');
            }
        }
        
        function updateStats() {
            const total = cleanedCards.length;
            const valid = cleanedCards.filter(c => c.valid).length;
            const invalid = cleanedCards.filter(c => !c.valid).length;
            const duplicates = cleanedCards.filter(c => c.duplicate).length;
            
            $('#totalCount').text(total);
            $('#validCount').text(valid);
            $('#invalidCount').text(invalid);
            $('#duplicateCount').text(duplicates);
            $('#resultCount').text(total);
        }
        
        function renderResults() {
            let filtered = cleanedCards;
            if (currentFilter === 'valid') filtered = cleanedCards.filter(c => c.valid);
            if (currentFilter === 'invalid') filtered = cleanedCards.filter(c => !c.valid);
            
            if (filtered.length === 0) {
                $('#resultsContainer').html('<div class="result-item header"><div>Card Number</div><div>Expiry</div><div>CVV</div><div>Brand</div><div>Status</div><div>Action</div></div><div class="result-item"><div colspan="6" style="text-align: center; color: var(--text-muted); padding: 1rem;">No cards to display</div></div>');
                return;
            }
            
            let html = '<div class="result-item header"><div>Card Number</div><div>Expiry</div><div>CVV</div><div>Brand</div><div>Status</div><div>Action</div></div>';
            
            filtered.forEach((card, idx) => {
                const statusClass = card.valid ? 'badge-success' : 'badge-danger';
                const statusText = card.valid ? 'Valid ✓' : 'Invalid ✗';
                const fullCard = `${card.number}|${card.month}|${card.year.slice(-2)}|${card.cvv}`;
                
                html += `
                    <div class="result-item">
                        <div>
                            ${card.number}
                            <i class="fas fa-copy copy-icon" onclick="copyText('${card.number}', 'Card number copied')"></i>
                        </div>
                        <div>
                            ${card.month}|${card.year.slice(-2)}
                            <i class="fas fa-copy copy-icon" onclick="copyText('${card.month}|${card.year.slice(-2)}', 'Expiry copied')"></i>
                        </div>
                        <div>
                            ${card.cvv}
                            <i class="fas fa-copy copy-icon" onclick="copyText('${card.cvv}', 'CVV copied')"></i>
                        </div>
                        <div>${card.brand}</div>
                        <div><span class="badge ${statusClass}">${statusText}</span></div>
                        <div>
                            <button class="btn btn-sm" onclick="copyText('${fullCard}', 'Full card copied')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                    </div>
                `;
            });
            
            $('#resultsContainer').html(html);
        }
        
        function copyText(text, message) {
            navigator.clipboard.writeText(text).then(() => {
                Swal.fire({
                    title: 'Copied!',
                    text: message,
                    icon: 'success',
                    toast: true,
                    timer: 1000,
                    showConfirmButton: false,
                    position: 'top-end'
                });
            });
        }
        
        function splitCards() {
            if (cleanedCards.length === 0) {
                Swal.fire('No cards', 'Clean cards first', 'warning');
                return;
            }
            
            const splitNum = parseInt($('#splitCount').val());
            if (!splitNum || splitNum < 2) {
                Swal.fire('Error', 'Enter a valid split number (2 or more)', 'error');
                return;
            }
            
            const total = cleanedCards.length;
            const partSize = Math.ceil(total / splitNum);
            let result = '';
            
            for (let i = 0; i < splitNum; i++) {
                const start = i * partSize;
                const end = Math.min(start + partSize, total);
                const partCards = cleanedCards.slice(start, end);
                
                result += `=== Part ${i + 1} (${partCards.length} cards) ===\n`;
                partCards.forEach(card => {
                    result += `${card.number}|${card.month}|${card.year.slice(-2)}|${card.cvv}\n`;
                });
                result += '\n';
            }
            
            $('#cardInput').val(result);
            Swal.fire('Split Complete', `Split ${total} cards into ${splitNum} parts`, 'success');
        }
        
        function downloadCleaned() {
            if (cleanedCards.length === 0) {
                Swal.fire('No cards', 'No cards to download', 'warning');
                return;
            }
            
            let content = '';
            cleanedCards.forEach(card => {
                content += `${card.number}|${card.month}|${card.year.slice(-2)}|${card.cvv}\n`;
            });
            
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `cleaned_cards_${new Date().toISOString().slice(0,19)}.txt`;
            a.click();
            URL.revokeObjectURL(url);
            
            Swal.fire('Downloaded', `${cleanedCards.length} cards downloaded`, 'success');
        }
        
        function importFromFile() {
            $('#fileInput').click();
        }
        
        $('#fileInput').on('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#cardInput').val(e.target.result);
                Swal.fire('Imported', `File loaded: ${file.name}`, 'success');
            };
            reader.readAsText(file);
        });
        
        function exportToFile() {
            const currentInput = $('#cardInput').val();
            if (!currentInput.trim()) {
                Swal.fire('No content', 'Nothing to export', 'warning');
                return;
            }
            
            const blob = new Blob([currentInput], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `cards_export_${new Date().toISOString().slice(0,19)}.txt`;
            a.click();
            URL.revokeObjectURL(url);
            
            Swal.fire('Exported', 'Cards exported to file', 'success');
        }
        
        function saveToStorage() {
            if (cleanedCards.length === 0) {
                Swal.fire('No cards', 'Clean cards first', 'warning');
                return;
            }
            
            localStorage.setItem('saved_cleaned_cards', JSON.stringify(cleanedCards));
            Swal.fire('Saved', `${cleanedCards.length} cards saved to storage`, 'success');
        }
        
        function loadFromStorage() {
            const saved = localStorage.getItem('saved_cleaned_cards');
            if (!saved) {
                Swal.fire('No saved cards', 'No cards found in storage', 'warning');
                return;
            }
            
            cleanedCards = JSON.parse(saved);
            updateStats();
            renderResults();
            
            Swal.fire('Loaded', `${cleanedCards.length} cards loaded from storage`, 'success');
        }
        
        async function checkWithGate() {
            if (cleanedCards.length === 0) {
                Swal.fire('No cards', 'Clean cards first', 'warning');
                return;
            }
            
            const gate = $('#gateSelect').val();
            if (!gate) {
                Swal.fire('Select Gate', 'Please select a gate to check with', 'warning');
                return;
            }
            
            Swal.fire({
                title: 'Checking Cards',
                text: `Checking ${cleanedCards.length} cards with ${gate}...`,
                icon: 'info',
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            const results = [];
            for (let i = 0; i < cleanedCards.length; i++) {
                const card = cleanedCards[i];
                const fullCard = `${card.number}|${card.month}|${card.year.slice(-2)}|${card.cvv}`;
                
                try {
                    const response = await $.ajax({
                        url: `/gate/${gate}`,
                        method: 'GET',
                        data: { cc: fullCard },
                        timeout: 10000
                    });
                    
                    results.push({
                        card: fullCard,
                        result: response.Response || response.status || 'Unknown'
                    });
                } catch(e) {
                    results.push({
                        card: fullCard,
                        result: 'Error: ' + e.statusText
                    });
                }
                
                // Update progress
                const percent = ((i + 1) / cleanedCards.length) * 100;
                $('#progressFill').css('width', percent + '%');
                $('#progressBar').show();
            }
            
            $('#progressBar').hide();
            
            let output = '';
            results.forEach(r => {
                output += `${r.card} | ${r.result}\n`;
            });
            
            $('#cardInput').val(output);
            Swal.fire('Check Complete', `Checked ${results.length} cards`, 'success');
        }
        
        $('#cleanBtn').click(cleanCards);
        $('#splitBtn').click(splitCards);
        $('#downloadCleanBtn').click(downloadCleaned);
        $('#importBtn').click(importFromFile);
        $('#exportBtn').click(exportToFile);
        $('#saveToStorageBtn').click(saveToStorage);
        $('#loadFromStorageBtn').click(loadFromStorage);
        
        $('#checkWithGate').change(function() {
            if ($(this).is(':checked')) {
                checkWithGate();
                $(this).prop('checked', false);
            }
        });
        
        $('.filter-btn').click(function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            renderResults();
        });
        
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
        
        $('#cardInput').val('');
    </script>
</body>
</html>
