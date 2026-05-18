# WC CPF/CNPJ Unique Login

## Overview
WordPress/WooCommerce plugin that provides CPF and CNPJ (Brazilian tax ID) unique validation and document-based login functionality. A standalone demo server showcases the validation logic.

## Recent Changes
- 2026-05-07: Fix falso-positivo "CPF já cadastrado" para cliente logado (v1.4.2)
  - Novo método `WC_Document_Unique_Login::document_exists_for_other_user($meta_key, $value, $exclude_user_id)` que pergunta direto no SQL se existe doc em usuário diferente do informado (com `user_id != %d`)
  - Robusto contra dados legados duplicados — antes `get_user_by_document` retornava o primeiro match (LIMIT 1), que podia ser outro usuário e gerar falso-positivo
  - `validate_unique` (registro), `validate_checkout` (checkout) e AJAX (`wc_validate_document`) agora usam o novo método, todos passando `get_current_user_id()`
  - Função privada `is_duplicate()` reutilizada entre as duas validações do servidor
- 2026-05-07: Fix submit do cadastro em "Minha Conta" (v1.4.1)
  - Substituído fluxo assíncrono `preventDefault → trigger('submit')` por validação síncrona
  - Submit nativo só é bloqueado se documento for inválido localmente; caso contrário, segue para outros plugins (reCAPTCHA, password meter etc.) sem interferência
  - Mesma função síncrona reutilizada no `checkout_place_order`
- 2026-05-07: Robustez no checkout (v1.4.0)
  - `beforeinput` event para bloqueio mais confiável que `keypress`
  - `MutationObserver` para detectar campos inseridos dinamicamente
  - CSS inline forçando feedback visual (`!important`)
  - Atributos `inputmode="numeric"`, `maxlength`, `autocomplete="off"`
- 2026-05-04: Revisão completa de consistência do plugin (v1.3.0)
  - Adicionada verificação de dependência do WooCommerce no bootstrap
  - Corrigido lock de campos para usar o ID do cliente editado (não do admin)
  - Substituído matching de texto hardcoded no login UI por busca parcial robusta
  - Trocadas classes CSS de tema por sistema de notices do WooCommerce
  - Versão do script agora usa constante WC_DOC_UL_VERSION
  - Corrigida indentação e organização em todos os arquivos
  - Text domain padronizado para 'wc-cpf-unique-login'
- 2026-05-04: Fix repeated validation alerts and false registration errors
- 2026-02-24: Initial Replit setup with PHP 8.4 and demo server
- 2026-03-16: Adicionado bloqueio de login de clientes via wp-admin (class-wc-document-block.php)

## Project Architecture
- **Plugin files**: Root directory contains the WordPress plugin (`wc-cpf-unique-login.php`, `includes/`, `assets/`)
- **Demo server**: `demo/` directory contains a standalone PHP demo showcasing CPF/CNPJ validation
  - `demo/index.php` - Main demo page with validation form
  - `demo/validator.php` - Standalone CPF/CNPJ validation logic

## Running
- PHP built-in server on port 5000 serving the `demo/` directory
- Command: `php -S 0.0.0.0:5000 -t demo`

## Key Plugin Components
- `class-wc-document-auth.php` - Authentication via CPF/CNPJ
- `class-wc-document-validator.php` - Unique document validation on registration
- `class-wc-document-ajax.php` - AJAX real-time validation
- `class-wc-document-lock.php` - Lock document fields after purchase
- `class-wc-document-login-ui.php` - Login form UI modifications
- `class-wc-document-block.php` - Bloqueio de login de clientes via wp-admin com exibição de mensagem personalizada

## Constants
- `WC_DOC_UL_VERSION` - Plugin version (used for script cache busting)
- `WC_DOC_UL_PATH` - Plugin directory path
- `WC_DOC_UL_URL` - Plugin directory URL
- `WC_Document_Unique_Login::META_CPF` = 'billing_cpf'
- `WC_Document_Unique_Login::META_CNPJ` = 'billing_cnpj'
- `WC_Document_Block::META_KEY` = 'wc_login_blocked'
- `WC_Document_Login_UI::LOGIN_LABEL` = 'CPF, CNPJ ou e-mail'
