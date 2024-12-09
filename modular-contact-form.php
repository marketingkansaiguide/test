<?php
/*
Plugin Name: Advanced Custom Contact Form
Description: Créez et gérez des formulaires personnalisés avec prise en charge multilingue et soumission AJAX.
Version: 1.5
Author: Votre Nom
*/

if (!defined('ABSPATH')) {
    exit; // Sécurisation
}
// Désactive l'ajout automatique des balises p et br
remove_filter('the_content', 'wpautop');
add_filter('the_content', 'wpautop', 12);

// === 1. Création d'une page d'administration ===
function accf_add_admin_menu() {
    add_menu_page(
        'Formulaires personnalisés',
        'Formulaires',
        'manage_options',
        'accf_admin',
        'accf_admin_page',
        'dashicons-feedback',
        20
    );
}
add_action('admin_menu', 'accf_add_admin_menu');

// === 2. Contenu de la page d'administration ===
function accf_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Retrieve all forms
    $forms = get_option('accf_forms', []);

    // Handle form saving (new or existing)
    if (isset($_POST['accf_save_form'])) {
        $form_id = sanitize_text_field($_POST['form_id']);
        $form_data = wp_unslash($_POST['form_data']); // Remove slashes added by WordPress
        $form_name = sanitize_text_field($_POST['form_name']); // Save form name

        // Validate JSON
        $json_decoded = json_decode($form_data);
        if ($json_decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            echo '<div class="error"><p>Erreur : Le JSON fourni est invalide. ' . json_last_error_msg() . '</p></div>';
        } else {
            // Save form data and name
            $forms[$form_id] = [
                'data' => $form_data,
                'name' => $form_name,
            ];
            update_option('accf_forms', $forms, false);
            echo '<div class="updated"><p>Formulaire sauvegardé avec succès.</p></div>';
        }
    }

    // Handle form deletion
    if (isset($_POST['accf_delete_form'])) {
        $form_id = sanitize_text_field($_POST['form_id']);
        unset($forms[$form_id]);
        update_option('accf_forms', $forms, false);
        echo '<div class="updated"><p>Formulaire supprimé avec succès.</p></div>';
    }

    // Handle global settings saving
    if (isset($_POST['accf_save_settings'])) {
        $messages = array(
            'success' => wp_kses_post(stripslashes($_POST['success_message'])),
            'error' => wp_kses_post(stripslashes($_POST['error_message']))
        );
        update_option('accf_messages', $messages, false);

        $email_settings = array(
            'subject' => sanitize_text_field(stripslashes($_POST['email_subject'])),
            'template' => wp_kses_post(stripslashes($_POST['email_template']))
        );
        update_option('accf_email_settings', $email_settings, false);

        echo '<div class="updated"><p>Paramètres sauvegardés avec succès.</p></div>';
    }

    // Retrieve global settings
    $messages = get_option('accf_messages', array(
        'success' => 'Votre message a été envoyé avec succès !',
        'error' => 'Une erreur est survenue lors de l\'envoi du message.'
    ));
    $email_settings = get_option('accf_email_settings', array(
        'subject' => 'Nouveau message via le formulaire de contact',
        'template' => "Nouveau message reçu :\n\n{content}"
    ));

    // Retrieve selected form for editing
    $selected_form_id = isset($_POST['selected_form_id']) ? sanitize_text_field($_POST['selected_form_id']) : '';
    $selected_form_data = isset($forms[$selected_form_id]['data']) ? $forms[$selected_form_id]['data'] : ''; // Use raw JSON string
    $selected_form_name = isset($forms[$selected_form_id]['name']) ? sanitize_text_field($forms[$selected_form_id]['name']) : '';

?>
<div class="wrap">
    <h1>Gestion des Formulaires</h1>

    <!-- Dropdown for selecting forms -->
    <h2>Modifier un Formulaire</h2>
    <form method="post">
        <label for="selected_form_id">Sélectionnez un formulaire :</label>
        <select name="selected_form_id" id="selected_form_id" onchange="this.form.submit();">
            <option value="">-- Nouveau Formulaire --</option>
            <?php foreach ($forms as $id => $data): ?>
                <option value="<?php echo esc_attr($id); ?>" <?php selected($selected_form_id, $id); ?>>
                    <?php echo esc_html($id); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit">Charger</button></noscript>
    </form>

    <!-- Form for adding or editing -->
    <h2>Ajouter ou Modifier un Formulaire</h2>
    <form method="post">
        <table class="form-table">
            <tr>
                <th>ID du formulaire</th>
                <td><input type="text" name="form_id" value="<?php echo esc_attr($selected_form_id); ?>" required class="regular-text"></td>
            </tr>
            <tr>
                <th>Nom du formulaire</th>
                <td><input type="text" name="form_name" value="<?php echo esc_attr($selected_form_name); ?>" required class="regular-text"></td>
            </tr>
            <tr>
                <th>Configuration du formulaire (JSON)</th>
                <td><textarea name="form_data" rows="10" style="width: 100%;"><?php echo esc_textarea($selected_form_data); ?></textarea></td>
            </tr>
        </table>
        <button type="submit" name="accf_save_form" class="button button-primary">Sauvegarder le formulaire</button>
    </form>

    <!-- Form deletion -->
    <?php if (!empty($selected_form_id)): ?>
        <form method="post" style="margin-top: 20px;">
            <input type="hidden" name="form_id" value="<?php echo esc_attr($selected_form_id); ?>">
            <button type="submit" name="accf_delete_form" class="button button-secondary">Supprimer ce formulaire</button>
        </form>
    <?php endif; ?>

    <!-- Global settings -->
    <h2>Configuration des Messages et Emails</h2>
    <form method="post">
        <table class="form-table">
            <tr>
                <th>Message de succès</th>
                <td><input type="text" name="success_message" value="<?php echo esc_attr($messages['success']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Message d'erreur</th>
                <td><input type="text" name="error_message" value="<?php echo esc_attr($messages['error']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Sujet de l'email</th>
                <td><input type="text" name="email_subject" value="<?php echo esc_attr($email_settings['subject']); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th>Template d'email</th>
                <td><textarea name="email_template" rows="10" class="large-text"><?php echo esc_textarea($email_settings['template']); ?></textarea></td>
            </tr>
        </table>
        <button type="submit" name="accf_save_settings" class="button button-primary">Sauvegarder les paramètres</button>
    </form>

</div>

<?php
}
// === 3. Génération dynamique du formulaire ===
function accf_render_form($atts) {
    remove_filter('the_content', 'wpautop');

    // Get attributes (e.g., [advanced_contact_form id="123"])
    $atts = shortcode_atts(['id' => ''], $atts);
    $form_id = sanitize_text_field($atts['id']); // Retrieve form ID from shortcode

    $forms = get_option('accf_forms', []);
    if (!isset($forms[$form_id])) {
        return '<p>Formulaire introuvable.</p>';
    }
    
        // Proceed with form rendering
    $fields = json_decode(wp_unslash($forms[$form_id]['data']), true);
    // Proceed with validation for $fields
    if (!isset($forms[$form_id])) {
        return '<p>Formulaire introuvable.</p>';
    }

    // Get form data and name
    $form_data = isset($forms[$form_id]['data']) ? wp_unslash($forms[$form_id]['data']) : '';
    $form_name = isset($forms[$form_id]['name']) ? esc_html($forms[$form_id]['name']) : 'Formulaire sans nom';

    // Decode form fields
    $fields = json_decode(wp_unslash($forms[$form_id]['data']), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '<p>Erreur dans la configuration du formulaire : ' . json_last_error_msg() . '</p>';
    }

    // Define the current language
    $current_language = function_exists('pll_current_language') ? pll_current_language() : 'fr';
    
    ob_start();
    ?>
    <div class="accf-form-wrapper">
    <form class="accf-form-class" id="accf-form<?php echo ($form_id != 1) ? '-' . esc_attr($form_id) : ''; ?>" method="post" enctype="multipart/form-data">        
    <div class="form-fields">
                <input type="hidden" name="action" value="accf_submit_form">
                <input type="hidden" name="security" value="<?php echo wp_create_nonce('accf_nonce'); ?>">
                <input type="hidden" id="whichform" name="quel_formulaire" value="<?php echo $form_name; ?>">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
                <?php foreach ($fields as $field): ?>
                    <?php if ($field['type'] === 'conditional-group'): ?>
                        <div class="conditional-group"
                             data-trigger-name="<?php echo esc_attr($field['trigger']['name']); ?>"
                             data-trigger-value="<?php echo esc_attr($field['trigger']['value']); ?>"
                             style="display: none;">
                            <?php foreach ($field['fields'] as $sub_field): ?>
                                <div class="form-field">
                                    <?php
                                    // Handle sub-field label
                                    $sub_label = $sub_field['label'][$current_language] ?? $sub_field['label']['fr'];
                                    if (is_array($sub_label)) {
                                        $sub_label = implode(', ', $sub_label);
                                    }
                                    ?>
                                    <label for="<?php echo esc_attr($sub_field['name']); ?>">
                                        <?php echo esc_html($sub_label); ?>
                                        <?php if (!empty($sub_field['required'])): ?> *<?php endif; ?>
                                    </label>
                                    <?php if ($sub_field['type'] === 'textarea'): ?>
                                        <textarea id="<?php echo esc_attr($sub_field['name']); ?>"
                                                  name="<?php echo esc_attr($sub_field['name']); ?>"
                                                  <?php echo !empty($sub_field['required']) ? 'required' : ''; ?>></textarea>
                                    <?php else: ?>
                                        <input type="<?php echo esc_attr($sub_field['type']); ?>"
                                               id="<?php echo esc_attr($sub_field['name']); ?>"
                                               name="<?php echo esc_attr($sub_field['name']); ?>"
                                               <?php echo !empty($sub_field['required']) ? 'required' : ''; ?>>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="form-field form-<?php echo esc_attr($field['name']); ?> form-<?php echo esc_attr($field['type']); ?>">
                            <?php
                            // Handle field label
                            $label = $field['label'][$current_language] ?? $field['label']['fr'];
                            if (is_array($label)) {
                                $label = implode(', ', $label);
                            }
                            ?>
                            <label for="<?php echo esc_attr($field['name']); ?>">
                                <?php echo esc_html($label); ?>
                                <?php if (!empty($field['required'])): ?> *<?php endif; ?>
                            </label>
                            <?php if ($field['type'] === 'date'): ?>
                            <input 
                                    type="date" 
                                    id="<?php echo esc_attr($field['name']); ?>" 
                                    name="<?php echo esc_attr($field['name']); ?>" 
                                    <?php if (!empty($field['min'])): ?>min="<?php echo esc_attr($field['min']); ?>"<?php endif; ?> 
                                    <?php if (!empty($field['max'])): ?>max="<?php echo esc_attr($field['max']); ?>"<?php endif; ?> 
                                    <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <?php elseif ($field['type'] === 'textarea'): ?>
                                <textarea id="<?php echo esc_attr($field['name']); ?>"
                                          name="<?php echo esc_attr($field['name']); ?>"
                                          <?php echo !empty($field['required']) ? 'required' : ''; ?>></textarea>
                            <?php elseif ($field['type'] === 'number'): ?>
                                <input  type="number" 
                                        id="<?php echo esc_attr($field['name']); ?>"
                                        name="<?php echo esc_attr($field['name']); ?>"
                                        min="1"
                                        <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <?php elseif ($field['type'] === 'radio'): ?>
                                <div class="radio-group">
                                    <?php foreach ($field['options'][$current_language] as $option): ?>
                                        <label class="radio-label">
                                            <input type="radio"
                                                   name="<?php echo esc_attr($field['name']); ?>"
                                                   value="<?php echo esc_attr($option); ?>"
                                                   <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                            <?php echo esc_html($option); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($field['type'] === 'checkbox'): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr($field['name']); ?>"
                                           value="1"
                                           <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php elseif ($field['type'] === 'range'): ?>
                                <div class="range-field">
                                    <input type="range"
                                           id="<?php echo esc_attr($field['name']); ?>"
                                           name="<?php echo esc_attr($field['name']); ?>"
                                           min="<?php echo esc_attr($field['min']); ?>"
                                           max="<?php echo esc_attr($field['max']); ?>"
                                           step="<?php echo esc_attr($field['step']); ?>"
                                           value="<?php echo esc_attr($field['value']); ?>"
                                           oninput="document.getElementById('<?php echo esc_attr($field['name']); ?>-output').textContent = this.value"
                                           <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                    <span id="<?php echo esc_attr($field['name']); ?>-output">
                                        <?php echo esc_html($field['value']); ?>
                                    </span>
                                </div>
                            <?php elseif ($field['type'] === 'file'): ?>
                                <input type="file"
                                       id="<?php echo esc_attr($field['name']); ?>"
                                       name="<?php echo esc_attr($field['name']); ?>"
                                       accept="<?php echo esc_attr(implode(',', array_map(function ($ext) {
                                           return '.' . $ext;
                                       }, $field['accept']))); ?>"
                                       <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                                <small>Types acceptés :
                                    <?php echo esc_html(implode(', ', $field['accept'])); ?></small>
                            <?php else: ?>
                                <input type="<?php echo esc_attr($field['type']); ?>"
                                       id="<?php echo esc_attr($field['name']); ?>"
                                       name="<?php echo esc_attr($field['name']); ?>"
                                       <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div id="submitcntform">
                    <button type="submit">Envoyer votre demande</button>
                </div>
            </div>
        </form>
        <div id="form-response-<?php echo esc_attr($form_id); ?>"></div>
    </div>

    <?php
    $content = ob_get_clean();

    // Réactive wpautop pour le reste du contenu
    add_filter('the_content', 'wpautop');

    // Nettoie explicitement les balises vides avant de retourner le contenu
    $content = preg_replace('/<p[^>]*><\/p>/', '', $content);
    $content = preg_replace('/<p>\s*<\/p>/', '', $content);

    return $content;
}
add_shortcode('advanced_contact_form', 'accf_render_form');

// === 4. Traitement AJAX du formulaire ===
function accf_handle_form_submission() {
    check_ajax_referer('accf_nonce', 'security');

    // Retrieve form fields and settings
    $fields = json_decode(wp_unslash(get_option('accf_form_data', '[]')), true);
    $messages = get_option('accf_messages', [
        'success' => 'Votre message a été envoyé avec succès !',
        'error' => 'Une erreur est survenue lors de l\'envoi du message.',
    ]);
    $email_settings = get_option('accf_email_settings', [
        'subject' => 'Nouveau message via le formulaire de contact',
    ]);

    // Initialize variables
    $errors = [];
    $submitted_data = [];
    $attachments = [];

    // Process form fields
    foreach ($fields as $field) {
        if ($field['type'] === 'file' && !empty($_FILES[$field['name']]['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $file = $_FILES[$field['name']];
            $allowed_types = isset($field['accept']) ? (array) $field['accept'] : [];
            $max_size = isset($field['max_size_mb']) ? intval($field['max_size_mb']) * 1024 * 1024 : 5242880; // Default 5MB

            // Validate file type and size
            if (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), $allowed_types)) {
                $errors[] = sprintf('Le type de fichier %s n\'est pas autorisé.', esc_html($file['name']));
                continue;
            }
            if ($file['size'] > $max_size) {
                $errors[] = sprintf('Le fichier %s dépasse la taille maximale autorisée.', esc_html($file['name']));
                continue;
            }

            // Handle file upload
            $upload_overrides = ['test_form' => false];
            $movefile = wp_handle_upload($file, $upload_overrides);
            if ($movefile && !isset($movefile['error'])) {
                $attachments[] = $movefile['file']; // Full path to the uploaded file
                $submitted_data[$field['name']] = basename($movefile['file']);
            } else {
                $errors[] = sprintf('Erreur lors du téléchargement du fichier %s : %s', esc_html($file['name']), esc_html($movefile['error']));
            }
        } else {
            // Handle other field types
            $value = isset($_POST[$field['name']]) ? sanitize_text_field($_POST[$field['name']]) : '';
            if (!empty($field['required']) && $field['required'] === true && empty($value)) {
                $errors[] = sprintf('%s est requis.', esc_html($field['label']['fr']));
            }
            $submitted_data[$field['name']] = $value;

        }
    }

    // Return errors if any
    if (!empty($errors)) {
        wp_send_json_error(['message' => implode('<br>', $errors)]);
        return;
    }

    // Prepare email content
    ob_start();
    ?>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        h2 { color: #555; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { text-align: left; padding: 8px; border: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
    </style>
</head>
<body>
<h2>Nouveau message reçu</h2>
<p><strong>Formulaire utilisé :</strong> <?php echo esc_html(get_option('form_name', 'Formulaire sans nom')); ?></p>
<p><strong>Page d'origine :</strong> <?php echo esc_html($_POST['_wp_http_referer']); ?></p>
<p><strong>URL de la page :</strong> <a href="<?php echo esc_url($_POST['_wp_http_referer']); ?>"><?php echo esc_url($_POST['_wp_http_referer']); ?></a></p>
<table>
    <tr><th>Champ</th><th>Valeur</th></tr>
    <?php foreach ($submitted_data as $key => $value): ?>
        <tr><td><?php echo esc_html($key); ?></td><td><?php echo esc_html($value); ?></td></tr>
    <?php endforeach; ?>
</table>
</body>
</html>
<?php
    $email_content = ob_get_clean();

    // Set email headers for HTML content
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // Send email
    wp_mail(
        get_option('admin_email'), // Recipient email address
        esc_html($email_settings['subject']), // Subject
        $email_content, // Message (HTML content)
        $headers, // Headers
        $attachments // Attachments (if any)
    );

    wp_send_json_success(['message' => esc_html($messages['success'])]);
}
add_action('wp_ajax_accf_submit_form', 'accf_handle_form_submission');
add_action('wp_ajax_nopriv_accf_submit_form', 'accf_handle_form_submission');
// === 5. Enregistrement des scripts et styles ===
function accf_enqueue_scripts() {
    wp_enqueue_script(
        'accf-script',
        plugin_dir_url(__FILE__) . 'accf-script.js',
        array('jquery'),
        '1.0',
        true
    );

    wp_localize_script('accf-script', 'accf_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('accf_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'accf_enqueue_scripts');