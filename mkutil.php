<?php
if (php_sapi_name() !== 'cli') {
    echo "⚠️ Please run this script from the command line (CLI).\n";
    exit(1);
}

// === Command Line Arguments ===
$pageName      = null;
$fieldInput    = '';
$shouldMigrate = false;
$useDefaults   = false;

// Basic CLI parsing
foreach ($argv as $i => $arg) {
    if ($i === 0)
        continue;  // skip script name
    if ($i === 1) {
        $pageName = $arg;
        continue;
    }

    if ($arg === '--migrate' || $arg === '-m') {
        $shouldMigrate = true;
    } elseif ($arg === '--defaults' || $arg === '-d') {
        $useDefaults = true;
    } elseif (!str_starts_with($arg, '-')) {
        // First non-flag argument after $pageName = fieldInput
        $fieldInput = $arg;
    }
}

// === HELP and LIST Command Flags ===
if (in_array('--help', $argv) || in_array('-h', $argv)) {
    echo <<<HELP

        📘 Usage:
            php mkutil.php <page_name> "field:type,field:type"
            php mkutil.php <page_name> "field:type,field:type" --migrate
            php mkutil.php <page_name> --defaults
            php mkutil.php <page_name> --defaults --migrate

        📦 Example:
            php mkutil.php student_form "name:text,age:number,class:select"

        🛠 Field Types Supported:
            text, number, password, email, date, time, datetime-local,
            select, textarea, checkbox, switch, radio, hidden, file

        📂 Utilities:
            --list, -l      Show available templates from defaults.json
            --defaults, -d  Use default fields from defaults.json
            --migrate, -m   Run database migrations
            --help, -h      Show this help message

        HELP;
    exit;
}

if (in_array('--list', $argv) || in_array('-l', $argv)) {
    $defaultsPath = __DIR__ . '/stubs/defaults.json';

    if (!file_exists($defaultsPath)) {
        echo "❌ Missing config file: stubs/defaults.json\n";
        exit(1);
    }

    $defaultUtilities = json_decode(file_get_contents($defaultsPath), true);
    if (!is_array($defaultUtilities)) {
        echo "❌ Invalid JSON format in defaults.json.\n";
        exit(1);
    }

    echo "📚 Available utility templates:\n\n";
    foreach ($defaultUtilities as $key => $value) {
        echo "🔹 {$key}\n";
        echo "    → Fields: {$value}\n\n";
    }
    exit;
}

// === Validate field input format ===
$validFieldTypes = [
    'text', 'number', 'password', 'email', 'date', 'time',
    'datetime-local', 'select', 'hidden', 'checkbox', 'radio', 'file', 'textarea'
];

// === Validate Page Name ===
if (!$pageName) {
    echo "❌ Usage: php mkutil.php page_name \"field:type,field:type\"\n";
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_-]+$/', $pageName)) {
    echo "❌ Invalid page name. Use only letters, numbers, underscores, and dashes.\n";
    exit(1);
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
if ($useDefaults || empty($fieldInput)) {
    $matchedUtility = null;

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
    $type  = strtolower($parts[1] ?? 'text');

    if (!$name) {
        echo "⚠️ Skipping malformed field definition: '{$field}'\n";
        continue;
    }

    if (!in_array($type, $validFieldTypes)) {
        echo "⚠️ Invalid type '{$type}' for field '{$name}'. Defaulting to 'text'.\n";
        $type = 'text';
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
                                            <option value=""></option>
                                        </select>
                                    </div>
                                </div>
            HTML;
    } elseif ($type === 'textarea') {
        $formFields .= <<<HTML

                                <!-- {$label} Field -->
                                <div class="form-group form-float">
                                    <label for="{$name}">{$label}</label>
                                    <div class="form-line">
                                        <textarea rows="1" class="form-control no-resize auto-growth" 
                                                placeholder="Please type what you want... And please don't forget the ENTER key press multiple times :)" style="overflow: hidden; overflow-wrap: break-word; height: 32px;"
                                                id="{$name}" name="{$name}" spellcheck="false"></textarea>
                                    </div>
                                </div>
            HTML;
    } elseif ($type === 'checkbox') {
        $formFields .= <<<HTML

                                <!-- {$label} Field -->
                                <div class="form-group">
                                    <input type="checkbox" id="{$name}" name="{$name}" class="filled-in chk-col-red">
                                    <label for="{$name}">{$label}</label>
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

// === Replace template placeholders ===
$page_name    = strtolower(str_replace('_', '-', $pageName));
$replacements = [
    '{{PAGE_NAME}}'     => $page_name,
    '{{PAGE_INFO}}'     => $pageName,
    '{{TITLE}}'         => $title,
    '{{TABLE_HEADERS}}' => $tableHeaders,
    '{{FORM_FIELDS}}'   => $formFields
];

foreach ($replacements as $key => $value) {
    $mainTemplate  = str_replace($key, $value, $mainTemplate);
    $fetchTemplate = str_replace($key, $value, $fetchTemplate);
    $postTemplate  = str_replace($key, $value, $postTemplate);
}

if ($shouldMigrate) {
    require_once 'config.php';           // Load DB config
    require_once 'model/pdo.class.php';  // Ensure DB class is loaded

    $tableName = "tbl_{$pageName}";

    $primaryKey = null;
    $sqlFields  = [];

    foreach ($fields as $i => $f) {
        $name = $f['name'];
        $type = $f['type'];

        // Check if first and hidden = primary
        if ($i === 0 && $type === 'hidden') {
            $primaryKey = $name;
            continue;  // we'll add it manually at the top
        }

        switch ($type) {
            case 'number':
                $sqlType = 'INT';
                break;
            case 'date':
                $sqlType = 'DATE';
                break;
            case 'time':
                $sqlType = 'TIME';
                break;
            case 'datetime-local':
                $sqlType = 'DATETIME';
                break;
            case 'checkbox':
            case 'switch':
            case 'radio':
                $sqlType = 'VARCHAR(50)';  // Store as string
                break;
            case 'file':
                $sqlType = 'LONGBLOB';
                break;
            case 'textarea':
                $sqlType = 'TEXT';
                break;
            case 'select':
                $sqlType = (preg_match('/_id$/', $name)) ? 'INT' : 'VARCHAR(255)';
                break;
            default:
                $sqlType = 'VARCHAR(255)';
        }
        $sqlFields[] = "`$name` $sqlType";
    }

    // Now create the actual SQL
    if (!$primaryKey) {
        echo "❌ Cannot determine primary key. First field must be type 'hidden'.\n";
        exit(1);
    }

    $createSQL = "CREATE TABLE IF NOT EXISTS `$tableName` (
    `$primaryKey` INT AUTO_INCREMENT PRIMARY KEY,
    " . implode(",\n    ", $sqlFields) . '
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;';

    // Run the SQL using your custom DB class
    try {
        $db = DB::getInstance();
        $db->run($createSQL);
        echo "✅ MySQL table '{$tableName}' created (if not exists).\n";
    } catch (Exception $e) {
        echo '❌ Failed to create table: ' . $e->getMessage() . "\n";
    }
}

// === Write generated files ===
$base = __DIR__ . '/modules';

$folders = ["$base/fetch", "$base/controller"];
foreach ($folders as $folder) {
    if (!is_dir($folder)) {
        if (!mkdir($folder, 0777, true)) {
            echo "❌ Failed to create directory: {$folder}\n";
            exit(1);
        }
    }
}

file_put_contents("$base/{$page_name}.php", $mainTemplate);
file_put_contents("$base/fetch/fetch-{$page_name}.php", $fetchTemplate);
file_put_contents("$base/controller/post-{$page_name}.php", $postTemplate);

echo "✅ Generated:\n";
echo "  modules/{$page_name}.php\n";
echo "  modules/fetch/fetch-{$page_name}.php\n";
echo "  modules/controller/post-{$page_name}.php\n";
echo "You can now implement the logic in the generated files.\n";

?>