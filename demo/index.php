<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/wp-stubs.php';
require_once __DIR__ . '/validator.php';

$result = null;
$doc_value = '';
$doc_type = 'cpf';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_value = $_POST['document'] ?? '';
    $doc_type = $_POST['type'] ?? 'cpf';

    $normalized = WC_Document_Unique_Login::normalize($doc_value);

    $validator = new DocumentValidator();
    $result = $validator->validate($normalized, $doc_type);

    $meta_key = ($doc_type === 'cnpj')
        ? WC_Document_Unique_Login::META_CNPJ
        : WC_Document_Unique_Login::META_CPF;
    $result['meta_key'] = $meta_key;
    $result['normalized'] = $normalized;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WC CPF/CNPJ Unique Login - Demo</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; color: #333; min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 40px 20px; }
        .container { max-width: 600px; width: 100%; }
        h1 { font-size: 1.5rem; margin-bottom: 8px; color: #7f54b3; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 0.95rem; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 30px; margin-bottom: 20px; }
        .card h2 { font-size: 1.1rem; margin-bottom: 16px; color: #444; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }
        input[type="text"], select { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s; }
        input[type="text"]:focus, select:focus { outline: none; border-color: #7f54b3; }
        button { background: #7f54b3; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-size: 1rem; cursor: pointer; width: 100%; transition: background 0.2s; }
        button:hover { background: #6b4199; }
        .result { margin-top: 16px; padding: 14px; border-radius: 8px; font-size: 0.95rem; }
        .result.valid { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .result.invalid { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .result .meta { font-size: 0.85rem; margin-top: 6px; opacity: 0.8; }
        .note { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 14px; margin-bottom: 20px; font-size: 0.9rem; color: #856404; }
        .info { background: #e8f4fd; border-radius: 12px; padding: 20px; margin-top: 20px; }
        .info h3 { font-size: 1rem; margin-bottom: 10px; color: #0c5460; }
        .info ul { padding-left: 20px; }
        .info li { margin-bottom: 6px; font-size: 0.9rem; color: #0c5460; }
        .plugin-files { margin-top: 20px; }
        .plugin-files h3 { font-size: 1rem; margin-bottom: 10px; }
        .file-list { list-style: none; padding: 0; }
        .file-list li { padding: 8px 12px; background: #f8f9fa; border-radius: 6px; margin-bottom: 4px; font-family: monospace; font-size: 0.85rem; color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h1>WC CPF/CNPJ Unique Login</h1>
        <p class="subtitle">WordPress/WooCommerce Plugin - Demo</p>

        <div class="note">
            This is a standalone demo of the plugin's validation logic. The full plugin requires WordPress + WooCommerce to run, providing document-based login, AJAX validation, uniqueness checks, and field locking.
        </div>

        <div class="card">
            <h2>Document Validator</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="type">Document Type</label>
                    <select name="type" id="type">
                        <option value="cpf" <?= $doc_type === 'cpf' ? 'selected' : '' ?>>CPF (11 digits)</option>
                        <option value="cnpj" <?= $doc_type === 'cnpj' ? 'selected' : '' ?>>CNPJ (14 digits)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="document">Document Number</label>
                    <input type="text" name="document" id="document" value="<?= htmlspecialchars($doc_value) ?>" placeholder="Enter CPF or CNPJ (with or without formatting)">
                </div>
                <button type="submit">Validate</button>
            </form>

            <?php if ($result !== null): ?>
                <div class="result <?= $result['valid'] ? 'valid' : 'invalid' ?>">
                    <?= htmlspecialchars($result['message']) ?>
                    <?php if (!empty($result['normalized'])): ?>
                        <div class="meta">
                            Normalized (via plugin): <?= htmlspecialchars($result['normalized']) ?>
                            | Meta key: <?= htmlspecialchars($result['meta_key']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="info">
            <h3>Plugin Features (requires WordPress + WooCommerce)</h3>
            <ul>
                <li>Unique CPF and CNPJ enforcement on WooCommerce registration</li>
                <li>Login using CPF/CNPJ document instead of username</li>
                <li>AJAX real-time document duplicate checking</li>
                <li>Document field locking after first purchase</li>
                <li>Runs on My Account and Checkout pages</li>
            </ul>
        </div>

        <div class="card plugin-files">
            <h3>Plugin Structure</h3>
            <ul class="file-list">
                <li>wc-cpf-unique-login.php (Main plugin file)</li>
                <li>includes/class-wc-document-unique-login.php</li>
                <li>includes/class-wc-document-auth.php</li>
                <li>includes/class-wc-document-ajax.php</li>
                <li>includes/class-wc-document-validator.php</li>
                <li>includes/class-wc-document-lock.php</li>
                <li>includes/class-wc-document-login-ui.php</li>
                <li>assets/js/document-validation.js</li>
            </ul>
        </div>
    </div>
</body>
</html>
