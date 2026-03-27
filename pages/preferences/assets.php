<?php
require_once __DIR__ . "/../../includes/config.php";
if (!isLoggedIn()) { 
    header('Location: /login.php'); 
    exit; 
}

$pageTitle = "Assets Manager";
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
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; font-size: 12px; }
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
        .page-title { font-size: 1.3rem; font-weight: 700; background: linear-gradient(135deg, var(--primary), #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .page-subtitle { color: var(--text-muted); font-size: 0.7rem; margin-top: 0.2rem; }
        
        .tabs { display: flex; gap: 0.3rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); }
        .tab { padding: 0.5rem 1rem; cursor: pointer; font-size: 0.75rem; border-bottom: 2px solid transparent; transition: all 0.2s; }
        .tab.active { color: var(--primary); border-bottom-color: var(--primary); }
        .tab:hover { color: var(--primary); }
        
        .card-section { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 1rem; margin-bottom: 1rem; }
        .section-title { font-size: 0.85rem; font-weight: 600; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .input-group { margin-bottom: 0.8rem; }
        .input-group label { display: block; font-size: 0.65rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.2rem; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 0.4rem; padding: 0.4rem 0.6rem; color: var(--text); font-size: 0.75rem; font-family: monospace; }
        
        .btn { padding: 0.4rem 0.8rem; border-radius: 0.4rem; font-weight: 500; font-size: 0.7rem; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #7c3aed); color: white; }
        .btn-success { background: linear-gradient(135deg, var(--success), #059669); color: white; }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-warning { background: linear-gradient(135deg, var(--warning), #d97706); color: white; }
        .btn-secondary { background: var(--bg); border: 1px solid var(--border); color: var(--text); }
        .btn-sm { padding: 0.2rem 0.5rem; font-size: 0.65rem; }
        
        .assets-list { max-height: 500px; overflow-y: auto; }
        .asset-item { padding: 0.6rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .asset-item:hover { background: var(--bg); }
        .asset-name { font-weight: 600; font-size: 0.75rem; }
        .asset-value { font-family: monospace; font-size: 0.65rem; color: var(--text-muted); }
        .asset-date { font-size: 0.55rem; color: var(--text-muted); }
        
        .layout-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 768px) { .layout-2col { grid-template-columns: 1fr; } }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1rem; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 0.8rem; padding: 0.8rem; }
        .stat-number { font-size: 1.2rem; font-weight: 700; }
        .stat-label { font-size: 0.55rem; color: var(--text-muted); margin-top: 0.2rem; }
        
        .badge { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 0.25rem; font-size: 0.55rem; font-weight: 600; }
        .badge-stripe { background: #635bff; color: white; }
        .badge-paypal { background: #0070ba; color: white; }
        .badge-shopify { background: #96bf48; color: white; }
        .badge-bin { background: var(--info); color: white; }
        .badge-key { background: var(--warning); color: white; }
    </style>
</head>
<body data-theme="dark">
    <?php include __DIR__ . "/../../includes/header.php"; ?>
    <?php include __DIR__ . "/../../includes/sidebar.php"; ?>
    
    <main class="main" id="main">
        <div class="container">
            <div class="page-header">
                <h1 class="page-title">Assets Manager</h1>
                <p class="page-subtitle">Manage API keys, BIN lists, and saved credentials</p>
            </div>
            
            <!-- Stats Overview -->
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card"><div class="stat-number" id="apiKeysCount">0</div><div class="stat-label">API Keys</div></div>
                <div class="stat-card"><div class="stat-number" id="binCount">0</div><div class="stat-label">BINs</div></div>
                <div class="stat-card"><div class="stat-number" id="savedCardsCount">0</div><div class="stat-label">Saved Cards</div></div>
                <div class="stat-card"><div class="stat-number" id="notesCount">0</div><div class="stat-label">Notes</div></div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" data-tab="api-keys"><i class="fas fa-key"></i> API Keys</div>
                <div class="tab" data-tab="bins"><i class="fas fa-database"></i> BIN Database</div>
                <div class="tab" data-tab="cards"><i class="fas fa-credit-card"></i> Saved Cards</div>
                <div class="tab" data-tab="notes"><i class="fas fa-sticky-note"></i> Notes</div>
            </div>
            
            <!-- API Keys Tab -->
            <div id="tab-api-keys" class="tab-content active">
                <div class="layout-2col">
                    <div class="card-section">
                        <div class="section-title"><i class="fas fa-plus-circle"></i> Add API Key</div>
                        <div class="input-group">
                            <label>Service</label>
                            <select id="apiService">
                                <option value="stripe">Stripe</option>
                                <option value="paypal">PayPal</option>
                                <option value="shopify">Shopify</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Key Name</label>
                            <input type="text" id="apiName" placeholder="My Stripe Key">
                        </div>
                        <div class="input-group">
                            <label>API Key / Secret</label>
                            <input type="text" id="apiKey" placeholder="sk_live_...">
                        </div>
                        <div class="input-group">
                            <label>Notes</label>
                            <textarea id="apiNotes" rows="2" placeholder="Optional notes"></textarea>
                        </div>
                        <button class="btn btn-primary" id="saveApiBtn"><i class="fas fa-save"></i> Save API Key</button>
                    </div>
                    
                    <div class="card-section">
                        <div class="section-title"><i class="fas fa-list"></i> Saved API Keys</div>
                        <div id="apiKeysList" class="assets-list">
                            <div style="text-align: center; padding: 1rem;">No API keys saved</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- BIN Database Tab -->
            <div id="tab-bins" class="tab-content" style="display: none;">
                <div class="layout-2col">
                    <div class="card-section">
                        <div class="section-title"><i class="fas fa-plus-circle"></i> Add BIN</div>
                        <div class="input-group">
                            <label>BIN (First 6 digits)</label>
                            <input type="text" id="binNumber" placeholder="550989" maxlength="6">
                        </div>
                        <div class="input-group">
                            <label>Card Brand</label>
                            <select id="binBrand">
                                <option value="Visa">Visa</option>
                                <option value="Mastercard">Mastercard</option>
                                <option value="Amex">American Express</option>
                                <option value="Discover">Discover</option>
                                <option value="JCB">JCB</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Bank Name</label>
                            <input type="text" id="binBank" placeholder="Chase Bank">
                        </div>
                        <div class="input-group">
                            <label>Country</label>
                            <input type="text" id="binCountry" placeholder="USA">
                        </div>
                        <div class="input-group">
                            <label>Card Type</label>
                            <select id="binType">
                                <option value="credit">Credit</option>
                                <option value="debit">Debit</option>
                                <option value="prepaid">Prepaid</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" id="saveBinBtn"><i class="fas fa-save"></i> Save BIN</button>
                    </div>
                    
                    <div class="card-section">
                        <div class="section-title"><i class="fas fa-search"></i> Search BIN</div>
                        <div class="input-group">
                            <input type="text" id="binSearch" placeholder="Search by BIN or bank...">
                        </div>
                        <div id="binsList" class="assets-list" style="max-height: 400px;">
                            <div style="text-align: center; padding: 1rem;">No BINs saved</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Saved Cards Tab -->
            <div id="tab-cards" class="tab-content" style="display: none;">
                <div class="layout-2col">
                    <div class="card-section">
                        <div class="section-title"><i class="fas fa-plus-circle"></i> Add Card</div>
                        <div class="input-group">
                            <label>Card Number</label>
                            <input type="text" id="cardNumber" placeholder="5509890034877216">
                        </div>
                        <div class="input-group">
                            <label>Expiry (MM/YYYY)</label>
                            <input type="text" id="cardExpiry" placeholder="06/2028">
                        </div>
                        <div class="input-group">
                            <label>CVV</label>
                            <input type="text" id="cardCvv" placeholder="333">
                        </div>
                        <div class="input-group">
                            <label>Card Name / Notes</label>
                            <input type="text" id="cardName" placeholder="My Test Card">
                        </div>
                        <button class="btn btn-primary" id="saveCardBtn"><i class="fas fa-save"></i> Save Card</button>
                    </div>
                    
                    <div class="card-section">
                        <div class="section-title"><i class="fas fa-list"></i> Saved Cards</div>
                        <div id="cardsList" class="assets-list">
                            <div style="text-align: center; padding: 1rem;">No cards saved</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notes Tab -->
            <div id="tab-notes" class="tab-content" style="display: none;">
                <div class="layout-2col">
                    <div class="card-section">
                        <div class="section-title"><i class="fas fa-plus-circle"></i> Add Note</div>
                        <div class="input-group">
                            <label>Title</label>
                            <input type="text" id="noteTitle" placeholder="Note title">
                        </div>
                        <div class="input-group">
                            <label>Content</label>
                            <textarea id="noteContent" rows="5" placeholder="Write your notes here..."></textarea>
                        </div>
                        <button class="btn btn-primary" id="saveNoteBtn"><i class="fas fa-save"></i> Save Note</button>
                    </div>
                    
                    <div class="card-section">
                        <div class="section-title"><i class="fas fa-sticky-note"></i> My Notes</div>
                        <div id="notesList" class="assets-list">
                            <div style="text-align: center; padding: 1rem;">No notes saved</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        // Load assets from localStorage
        function loadAssets() {
            // Load API Keys
            let apiKeys = JSON.parse(localStorage.getItem('api_keys') || '[]');
            $('#apiKeysCount').text(apiKeys.length);
            
            if (apiKeys.length === 0) {
                $('#apiKeysList').html('<div style="text-align: center; padding: 1rem; color: var(--text-muted);">No API keys saved</div>');
            } else {
                let html = '';
                apiKeys.forEach((key, i) => {
                    let badgeClass = key.service === 'stripe' ? 'badge-stripe' : (key.service === 'paypal' ? 'badge-paypal' : 'badge-shopify');
                    html += `
                        <div class="asset-item">
                            <div>
                                <div class="asset-name"><span class="badge ${badgeClass}" style="margin-right: 0.5rem;">${key.service}</span> ${escapeHtml(key.name)}</div>
                                <div class="asset-value">${maskKey(key.key)}</div>
                                <div class="asset-date">${key.date}</div>
                            </div>
                            <div>
                                <button class="btn btn-secondary btn-sm copy-key" data-key="${escapeHtml(key.key)}"><i class="fas fa-copy"></i></button>
                                <button class="btn btn-danger btn-sm delete-api" data-index="${i}"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    `;
                });
                $('#apiKeysList').html(html);
            }
            
            // Load BINs
            let bins = JSON.parse(localStorage.getItem('bins') || '[]');
            $('#binCount').text(bins.length);
            renderBins(bins);
            
            // Load Cards
            let cards = JSON.parse(localStorage.getItem('saved_cards') || '[]');
            $('#savedCardsCount').text(cards.length);
            if (cards.length === 0) {
                $('#cardsList').html('<div style="text-align: center; padding: 1rem; color: var(--text-muted);">No cards saved</div>');
            } else {
                let html = '';
                cards.forEach((card, i) => {
                    html += `
                        <div class="asset-item">
                            <div>
                                <div class="asset-name">${escapeHtml(card.name || 'Card')}</div>
                                <div class="asset-value">**** **** **** ${card.number.slice(-4)} | Exp: ${card.expiry}</div>
                                <div class="asset-date">${card.date}</div>
                            </div>
                            <div>
                                <button class="btn btn-secondary btn-sm copy-card" data-card="${escapeHtml(card.number)}|${card.expiry.replace('/', '|')}|${card.cvv}"><i class="fas fa-copy"></i></button>
                                <button class="btn btn-danger btn-sm delete-card" data-index="${i}"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    `;
                });
                $('#cardsList').html(html);
            }
            
            // Load Notes
            let notes = JSON.parse(localStorage.getItem('notes') || '[]');
            $('#notesCount').text(notes.length);
            if (notes.length === 0) {
                $('#notesList').html('<div style="text-align: center; padding: 1rem; color: var(--text-muted);">No notes saved</div>');
            } else {
                let html = '';
                notes.forEach((note, i) => {
                    html += `
                        <div class="asset-item">
                            <div style="flex: 1;">
                                <div class="asset-name">${escapeHtml(note.title)}</div>
                                <div class="asset-value">${escapeHtml(note.content.substring(0, 100))}${note.content.length > 100 ? '...' : ''}</div>
                                <div class="asset-date">${note.date}</div>
                            </div>
                            <div>
                                <button class="btn btn-danger btn-sm delete-note" data-index="${i}"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    `;
                });
                $('#notesList').html(html);
            }
        }
        
        function renderBins(bins, search = '') {
            let filtered = bins;
            if (search) {
                filtered = bins.filter(b => b.bin.includes(search) || b.bank.toLowerCase().includes(search.toLowerCase()));
            }
            
            if (filtered.length === 0) {
                $('#binsList').html('<div style="text-align: center; padding: 1rem; color: var(--text-muted);">No BINs found</div>');
            } else {
                let html = '';
                filtered.forEach((bin, i) => {
                    html += `
                        <div class="asset-item">
                            <div style="flex: 1;">
                                <div class="asset-name"><span class="badge badge-bin">${bin.bin}</span> ${bin.brand} - ${bin.bank}</div>
                                <div class="asset-value">${bin.type} | ${bin.country}</div>
                                <div class="asset-date">Added: ${bin.date}</div>
                            </div>
                            <div>
                                <button class="btn btn-danger btn-sm delete-bin" data-index="${bins.findIndex(b => b.bin === bin.bin)}"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    `;
                });
                $('#binsList').html(html);
            }
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        function maskKey(key) {
            if (!key) return '';
            if (key.length <= 20) return key;
            return key.substring(0, 10) + '...' + key.substring(key.length - 5);
        }
        
        // Tab switching
        $('.tab').click(function() {
            $('.tab').removeClass('active');
            $(this).addClass('active');
            $('.tab-content').hide();
            $('#tab-' + $(this).data('tab')).show();
        });
        
        // Save API Key
        $('#saveApiBtn').click(function() {
            let apiKeys = JSON.parse(localStorage.getItem('api_keys') || '[]');
            apiKeys.push({
                service: $('#apiService').val(),
                name: $('#apiName').val(),
                key: $('#apiKey').val(),
                notes: $('#apiNotes').val(),
                date: new Date().toLocaleString()
            });
            localStorage.setItem('api_keys', JSON.stringify(apiKeys));
            $('#apiName, #apiKey, #apiNotes').val('');
            loadAssets();
            Swal.fire('Saved', 'API key saved successfully', 'success');
        });
        
        // Save BIN
        $('#saveBinBtn').click(function() {
            let bins = JSON.parse(localStorage.getItem('bins') || '[]');
            let bin = $('#binNumber').val().trim();
            if (bin.length !== 6) {
                Swal.fire('Error', 'BIN must be exactly 6 digits', 'error');
                return;
            }
            bins.push({
                bin: bin,
                brand: $('#binBrand').val(),
                bank: $('#binBank').val(),
                country: $('#binCountry').val(),
                type: $('#binType').val(),
                date: new Date().toLocaleString()
            });
            localStorage.setItem('bins', JSON.stringify(bins));
            $('#binNumber, #binBank, #binCountry').val('');
            loadAssets();
            Swal.fire('Saved', 'BIN saved successfully', 'success');
        });
        
        // Search BIN
        $('#binSearch').on('input', function() {
            let bins = JSON.parse(localStorage.getItem('bins') || '[]');
            renderBins(bins, $(this).val());
        });
        
        // Save Card
        $('#saveCardBtn').click(function() {
            let cards = JSON.parse(localStorage.getItem('saved_cards') || '[]');
            let cardNum = $('#cardNumber').val().trim();
            let expiry = $('#cardExpiry').val().trim();
            let cvv = $('#cardCvv').val().trim();
            
            if (!cardNum || !expiry || !cvv) {
                Swal.fire('Error', 'Please fill all card details', 'error');
                return;
            }
            
            cards.push({
                number: cardNum,
                expiry: expiry,
                cvv: cvv,
                name: $('#cardName').val(),
                date: new Date().toLocaleString()
            });
            localStorage.setItem('saved_cards', JSON.stringify(cards));
            $('#cardNumber, #cardExpiry, #cardCvv, #cardName').val('');
            loadAssets();
            Swal.fire('Saved', 'Card saved successfully', 'success');
        });
        
        // Save Note
        $('#saveNoteBtn').click(function() {
            let notes = JSON.parse(localStorage.getItem('notes') || '[]');
            let title = $('#noteTitle').val().trim();
            let content = $('#noteContent').val().trim();
            
            if (!title) {
                Swal.fire('Error', 'Please enter a title', 'error');
                return;
            }
            
            notes.push({
                title: title,
                content: content,
                date: new Date().toLocaleString()
            });
            localStorage.setItem('notes', JSON.stringify(notes));
            $('#noteTitle, #noteContent').val('');
            loadAssets();
            Swal.fire('Saved', 'Note saved successfully', 'success');
        });
        
        // Delete handlers (event delegation)
        $(document).on('click', '.delete-api', function() {
            let index = $(this).data('index');
            let apiKeys = JSON.parse(localStorage.getItem('api_keys') || '[]');
            apiKeys.splice(index, 1);
            localStorage.setItem('api_keys', JSON.stringify(apiKeys));
            loadAssets();
            Swal.fire('Deleted', 'API key deleted', 'success');
        });
        
        $(document).on('click', '.delete-bin', function() {
            let index = $(this).data('index');
            let bins = JSON.parse(localStorage.getItem('bins') || '[]');
            bins.splice(index, 1);
            localStorage.setItem('bins', JSON.stringify(bins));
            loadAssets();
            Swal.fire('Deleted', 'BIN deleted', 'success');
        });
        
        $(document).on('click', '.delete-card', function() {
            let index = $(this).data('index');
            let cards = JSON.parse(localStorage.getItem('saved_cards') || '[]');
            cards.splice(index, 1);
            localStorage.setItem('saved_cards', JSON.stringify(cards));
            loadAssets();
            Swal.fire('Deleted', 'Card deleted', 'success');
        });
        
        $(document).on('click', '.delete-note', function() {
            let index = $(this).data('index');
            let notes = JSON.parse(localStorage.getItem('notes') || '[]');
            notes.splice(index, 1);
            localStorage.setItem('notes', JSON.stringify(notes));
            loadAssets();
            Swal.fire('Deleted', 'Note deleted', 'success');
        });
        
        $(document).on('click', '.copy-key', function() {
            let key = $(this).data('key');
            navigator.clipboard.writeText(key);
            Swal.fire('Copied!', 'Key copied to clipboard', 'success');
        });
        
        $(document).on('click', '.copy-card', function() {
            let card = $(this).data('card');
            navigator.clipboard.writeText(card);
            Swal.fire('Copied!', 'Card copied to clipboard', 'success');
        });
        
        // Initial load
        loadAssets();
        
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
    </script>
</body>
</html>
