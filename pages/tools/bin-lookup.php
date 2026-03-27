<?php
require_once __DIR__ . "/../../includes/config.php";
if (!isLoggedIn()) { 
    header('Location: /login.php'); 
    exit; 
}

$pageTitle = "BIN Lookup";
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
        
        .input-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; padding: 0.8rem; margin-bottom: 1rem; }
        .input-group { margin-bottom: 0.6rem; }
        .input-group label { display: block; font-size: 0.6rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.2rem; font-weight: 600; }
        .input-group input, .input-group textarea { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.4rem 0.6rem; color: var(--text); font-size: 0.7rem; font-family: monospace; }
        
        .btn { padding: 0.3rem 0.7rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.65rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.6rem; }
        
        .results-table { background: var(--card); border: 1px solid var(--border); border-radius: 0.6rem; overflow: hidden; }
        .results-header { padding: 0.5rem 0.8rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .results-header h3 { font-size: 0.7rem; font-weight: 600; }
        .result-item { padding: 0.4rem 0.8rem; border-bottom: 1px solid var(--border); display: grid; grid-template-columns: 80px 90px 100px 1fr 60px; gap: 0.5rem; font-size: 0.65rem; align-items: center; }
        .result-item.header { background: var(--bg); font-weight: 600; color: var(--text-muted); }
        .result-status { font-weight: 600; }
        .result-status.success { color: var(--success); }
        .result-status.error { color: var(--danger); }
        
        .badge { display: inline-block; padding: 0.1rem 0.3rem; border-radius: 0.2rem; font-size: 0.55rem; font-weight: 600; }
        .badge-visa { background: #1a1f71; color: white; }
        .badge-mastercard { background: #eb001b; color: white; }
        .badge-amex { background: #006fcf; color: white; }
        .badge-discover { background: #ff6000; color: white; }
        .badge-default { background: var(--text-muted); color: white; }
        
        .stats { display: flex; gap: 0.5rem; margin-bottom: 0.8rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.3rem 0.6rem; flex: 1; text-align: center; }
        .stat-number { font-size: 0.9rem; font-weight: 700; }
        .stat-label { font-size: 0.55rem; color: var(--text-muted); }
        
        .progress-bar { height: 2px; background: var(--border); margin-top: 0.5rem; border-radius: 2px; overflow: hidden; display: none; }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.3s; }
        
        @media (max-width: 768px) {
            .result-item { grid-template-columns: 70px 80px 90px 1fr 50px; gap: 0.3rem; font-size: 0.6rem; }
        }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">BIN Lookup</h1>
                <p class="page-subtitle">Accepts BINs or full cards • Bulk lookup with bank info</p>
            </div>
            
            <div class="stats">
                <div class="stat-card"><div class="stat-number" id="totalCount">0</div><div class="stat-label">Total</div></div>
                <div class="stat-card"><div class="stat-number" id="foundCount">0</div><div class="stat-label">Found</div></div>
                <div class="stat-card"><div class="stat-number" id="apiStatus">Ready</div><div class="stat-label">Status</div></div>
            </div>
            
            <div class="input-section">
                <div class="input-group">
                    <label><i class="fas fa-credit-card"></i> BINs or Full Cards (one per line)</label>
                    <textarea id="binInput" rows="4" placeholder="550989&#10;411111&#10;5509890034877216|06|2028|333" style="font-family: monospace; font-size: 0.7rem;"></textarea>
                </div>
                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" id="lookupBtn"><i class="fas fa-search"></i> Lookup</button>
                    <button class="btn btn-secondary" id="clearBtn"><i class="fas fa-trash"></i> Clear</button>
                    <button class="btn btn-secondary" id="copyBtn"><i class="fas fa-copy"></i> Copy Results</button>
                </div>
                <div class="progress-bar" id="progressBar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
            </div>
            
            <div class="results-table">
                <div class="results-header">
                    <h3><i class="fas fa-list"></i> Results (<span id="resultCount">0</span>)</h3>
                    <div class="filter-buttons" style="display: flex; gap: 0.3rem;">
                        <button class="btn btn-sm filter-btn active" data-filter="all">All</button>
                        <button class="btn btn-sm filter-btn" data-filter="found">Found</button>
                        <button class="btn btn-sm filter-btn" data-filter="notfound">Not Found</button>
                    </div>
                </div>
                <div id="resultsContainer">
                    <div class="result-item header">
                        <div>BIN</div>
                        <div>Brand</div>
                        <div>Type</div>
                        <div>Bank / Country</div>
                        <div></div>
                    </div>
                    <div class="result-item"><div colspan="5" style="text-align: center; color: var(--text-muted); padding: 1rem;">Enter BINs or full cards and click Lookup</div></div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        let results = [];
        let currentFilter = 'all';
        let isProcessing = false;
        
        function getBadgeClass(brand) {
            const b = (brand || '').toLowerCase();
            if (b.includes('visa')) return 'badge-visa';
            if (b.includes('master')) return 'badge-mastercard';
            if (b.includes('american') || b.includes('amex')) return 'badge-amex';
            if (b.includes('discover')) return 'badge-discover';
            return 'badge-default';
        }
        
        function extractBIN(input) {
            if (input.includes('|')) {
                const parts = input.split('|');
                if (parts.length >= 1) {
                    return parts[0].substring(0, 6);
                }
            }
            if (/^\d+$/.test(input)) {
                return input.substring(0, 6);
            }
            return null;
        }
        
        async function lookupSingleBIN(bin, originalInput, index, total) {
            return new Promise((resolve) => {
                const bin6 = bin.substring(0, 6);
                const percent = ((index + 1) / total) * 100;
                $('#progressFill').css('width', percent + '%');
                
                $.ajax({
                    url: `/gate/bin-lookup-api.php?bin=${bin6}`,
                    method: 'GET',
                    timeout: 10000,
                    success: function(data) {
                        if (data.success) {
                            resolve({
                                bin: bin6,
                                original: originalInput,
                                found: true,
                                brand: data.scheme || 'Unknown',
                                type: data.type || 'Unknown',
                                bank: data.bank || 'Unknown',
                                country: data.country || 'N/A',
                                countryName: data.country_name || data.country,
                                emoji: data.emoji || ''
                            });
                        } else {
                            resolve({
                                bin: bin6,
                                original: originalInput,
                                found: false,
                                brand: 'Unknown',
                                type: 'Unknown',
                                bank: 'Not found',
                                country: 'N/A'
                            });
                        }
                    },
                    error: function() {
                        resolve({
                            bin: bin6,
                            original: originalInput,
                            found: false,
                            brand: 'Unknown',
                            type: 'Unknown',
                            bank: 'API Error',
                            country: 'N/A'
                        });
                    }
                });
            });
        }
        
        async function lookupBulk(items) {
            const total = items.length;
            const newResults = [];
            
            $('#progressBar').show();
            $('#apiStatus').text('Loading...').css('color', 'var(--warning)');
            
            for (let i = 0; i < total; i++) {
                const bin = extractBIN(items[i]);
                if (bin && bin.length >= 6) {
                    const result = await lookupSingleBIN(bin, items[i], i, total);
                    newResults.push(result);
                } else {
                    newResults.push({
                        bin: 'Invalid',
                        original: items[i],
                        found: false,
                        brand: 'Invalid',
                        type: 'Invalid',
                        bank: 'Invalid format',
                        country: 'N/A'
                    });
                }
                
                results = newResults;
                updateResultsDisplay();
                
                if (i < total - 1) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
            }
            
            $('#progressBar').hide();
            $('#progressFill').css('width', '0%');
            
            const foundCount = results.filter(r => r.found).length;
            if (foundCount === results.length) {
                $('#apiStatus').text('Complete').css('color', 'var(--success)');
            } else {
                $('#apiStatus').text(`${foundCount}/${results.length} found`).css('color', 'var(--warning)');
            }
        }
        
        function updateResultsDisplay() {
            let filtered = results;
            if (currentFilter === 'found') filtered = results.filter(r => r.found);
            if (currentFilter === 'notfound') filtered = results.filter(r => !r.found);
            
            const foundCount = results.filter(r => r.found).length;
            $('#totalCount').text(results.length);
            $('#foundCount').text(foundCount);
            $('#resultCount').text(filtered.length);
            
            if (filtered.length === 0) {
                $('#resultsContainer').html('<div class="result-item header"><div>BIN</div><div>Brand</div><div>Type</div><div>Bank / Country</div><div></div></div><div class="result-item"><div colspan="5" style="text-align: center; color: var(--text-muted); padding: 1rem;">No results</div></div>');
                return;
            }
            
            let html = '<div class="result-item header"><div>BIN</div><div>Brand</div><div>Type</div><div>Bank / Country</div><div></div></div>';
            filtered.forEach(r => {
                const badgeClass = getBadgeClass(r.brand);
                const statusClass = r.found ? 'success' : 'error';
                const statusText = r.found ? '✓' : '✗';
                const flag = r.emoji || '🌍';
                const displayBin = r.bin === 'Invalid' ? r.original : r.bin;
                
                html += `
                    <div class="result-item">
                        <div><code>${displayBin}</code></div>
                        <div><span class="badge ${badgeClass}">${r.brand}</span></div>
                        <div>${r.type}</div>
                        <div>${r.bank} ${flag} ${r.countryName || r.country}</div>
                        <div class="result-status ${statusClass}">${statusText}</div>
                    </div>
                `;
            });
            $('#resultsContainer').html(html);
        }
        
        async function runLookup() {
            if (isProcessing) {
                Swal.fire('Busy', 'Please wait', 'warning');
                return;
            }
            
            const rawInput = $('#binInput').val();
            const items = rawInput.split('\n').map(l => l.trim()).filter(l => l.length > 0);
            
            if (items.length === 0) {
                Swal.fire('Error', 'Please enter BINs or full cards', 'error');
                return;
            }
            
            if (items.length > 100) {
                const confirm = await Swal.fire({
                    title: 'Large Request',
                    text: `Lookup ${items.length} items? This may take a moment.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes'
                });
                if (!confirm.isConfirmed) return;
            }
            
            isProcessing = true;
            $('#lookupBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            
            try {
                await lookupBulk(items);
            } catch (error) {
                Swal.fire('Error', 'Lookup failed', 'error');
            }
            
            isProcessing = false;
            $('#lookupBtn').prop('disabled', false).html('<i class="fas fa-search"></i> Lookup');
        }
        
        function copyResults() {
            if (results.length === 0) {
                Swal.fire('No results', 'Nothing to copy', 'warning');
                return;
            }
            
            let text = 'BIN\tBrand\tType\tBank\tCountry\n';
            results.forEach(r => {
                const displayBin = r.bin === 'Invalid' ? r.original : r.bin;
                text += `${displayBin}\t${r.brand}\t${r.type}\t${r.bank}\t${r.countryName || r.country}\n`;
            });
            navigator.clipboard.writeText(text);
            Swal.fire('Copied!', `${results.length} results`, 'success');
        }
        
        $('#lookupBtn').click(runLookup);
        $('#clearBtn').click(() => {
            $('#binInput').val('');
            results = [];
            updateResultsDisplay();
            $('#apiStatus').text('Ready').css('color', 'var(--text-muted)');
        });
        $('#copyBtn').click(copyResults);
        
        $('.filter-btn').click(function() {
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            updateResultsDisplay();
        });
        
        $('#binInput').keypress((e) => {
            if (e.which === 13 && e.ctrlKey) runLookup();
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
        
        $('#binInput').val('');
    </script>
</body>
</html>
