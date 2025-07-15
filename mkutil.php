<?php
$pageName   = $argv[1] ?? null;
$fieldInput = $argv[2] ?? '';

// === Validate Page Name ===
if (!$pageName) {
    echo "❌ Usage: php mkutil.php page_name \"field:type,field:type\"\n";
    exit;
}

// === Load default utility field definitions from JSON ===
$defaultsPath = __DIR__ . '/stubs/defaults.json';

if (!file_exists($defaultsPath)) {
    echo "❌ Missing config file: stubs/defaults.json\n";
    exit(1);
}

$defaultUtilities = json_decode(file_get_contents($defaultsPath), true);
if (!is_array($defaultUtilities)) {
    echo "❌ Invalid JSON format in defaults.json. Ensure it is a proper key-value object.\n";
    exit(1);
}

// === Use defaults if fields are not provided ===
$matchedUtility = null;

if (empty(trim($fieldInput))) {
    // Exact match first
    if (isset($defaultUtilities[$pageName])) {
        $matchedUtility = $pageName;
    } else {
        // Starts-with match only (stricter)
        foreach ($defaultUtilities as $key => $defaults) {
            if (stripos($pageName, $key) === 0) {
                $matchedUtility = $key;
                break;
            }
        }
    }

    if ($matchedUtility) {
        echo "ℹ️ Using default fields from '{$matchedUtility}' (matched start of '{$pageName}')\n";
        $fieldInput = $defaultUtilities[$matchedUtility];
    } else {
        echo "❌ No fields provided and '{$pageName}' doesn't match any known utility type.\n";
        echo "🔍 Available utility types (must start the name):\n";
        foreach (array_keys($defaultUtilities) as $util) {
            echo "  - {$util}\n";
        }
        exit;
    }
}

// === Setup metadata ===
$title   = strtoupper(str_replace('_', ' ', $pageName));
$fields  = [];
$columns = [];

// === Parse field definitions ===
$fieldParts = array_filter(array_map('trim', explode(',', $fieldInput)));
foreach ($fieldParts as $field) {
    $parts = explode(':', $field);
    $name  = $parts[0] ?? null;
    $type  = $parts[1] ?? 'text';

    if (!$name) {
        echo "⚠️ Skipping malformed field definition: '{$field}'\n";
        continue;
    }

    if (count($parts) < 2) {
        echo "⚠️ Warning: Field '{$field}' has no type specified. Defaulting to 'text'.\n";
    }

    $fields[]  = ['name' => $name, 'type' => $type];
    $columns[] = ucwords(str_replace('_', ' ', $name));
}

$columns[] = 'Actions';

// === Generate HTML table headers ===
$tableHeaders = '';
foreach ($columns as $col) {
    $tableHeaders .= "                                        <th>{$col}</th>\n";
}

// === Generate form fields ===
$formFields = '';
foreach ($fields as $f) {
    $name  = $f['name'];
    $type  = strtolower($f['type']);
    $label = ucwords(str_replace('_', ' ', $name));

    if ($type === 'hidden') {
        $formFields .= <<<HTML
                <input type="hidden" id="{$name}" name="{$name}">
            HTML;
        continue;
    }

    if ($type === 'select') {
        $formFields .= <<<HTML

                                <!-- {$label} Field -->
                                <div class="form-group form-float">
                                    <label for="{$name}">{$label}</label>
                                    <div class="form-line">
                                        <select name="{$name}" id="{$name}" class="form-control">
                                            <!-- Add your options here -->
                                        </select>
                                    </div>
                                </div>
            HTML;
    } else {
        $formFields .= <<<HTML

                                <!-- {$label} Field -->
                                <div class="form-group form-float">
                                    <label for="{$name}">{$label}</label>
                                    <div class="form-line">
                                        <input type="{$type}" id="{$name}" name="{$name}" class="form-control"
                                            placeholder="Enter {$label}">
                                    </div>
                                </div>
            HTML;
    }
}

// === Load stub templates (with error checks) ===
function safe_load_stub($path)
{
    if (!file_exists($path)) {
        echo "❌ Stub file missing: {$path}\n";
        exit(1);
    }
    return file_get_contents($path);
}

$mainTemplate  = safe_load_stub(__DIR__ . '/stubs/main.stub');
$fetchTemplate = safe_load_stub(__DIR__ . '/stubs/fetch.stub');
$postTemplate  = safe_load_stub(__DIR__ . '/stubs/post.stub');
$jsTemplate    = safe_load_stub(__DIR__ . '/stubs/js.stub');

// === Replace template placeholders ===
$replacements = [
    '{{PAGE_NAME}}'     => $pageName,
    '{{TITLE}}'         => $title,
    '{{TABLE_HEADERS}}' => $tableHeaders,
    '{{FORM_FIELDS}}'   => $formFields
];

foreach ($replacements as $key => $value) {
    $mainTemplate  = str_replace($key, $value, $mainTemplate);
    $fetchTemplate = str_replace($key, $value, $fetchTemplate);
    $postTemplate  = str_replace($key, $value, $postTemplate);
    $jsTemplate    = str_replace($key, $value, $jsTemplate);
}

// === Write generated files ===
$base = __DIR__ . '/modules';

$folders = ["$base/fetch", "$base/php", "$base/js"];
foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0777, true)) {
            echo "❌ Failed to create directory: {$folder}\n";
            exit(1);
        }
    }
}

file_put_contents("$base/{$pageName}.php", $mainTemplate);
file_put_contents("$base/fetch/fetch_{$pageName}.php", $fetchTemplate);
file_put_contents("$base/php/post_{$pageName}.php", $postTemplate);
file_put_contents("$base/js/{$pageName}.js", $jsTemplate);

echo "✅ Generated:\n";
echo "  modules/{$pageName}.php\n";
echo "  modules/fetch/fetch_{$pageName}.php\n";
echo "  modules/php/post_{$pageName}.php\n";
echo "  modules/js/{$pageName}.js\n";
echo "You can now implement the logic in the generated files.\n";
