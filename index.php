<?php
$db_file = __DIR__ . '/history.sqlite';
$pdo = new PDO('sqlite:' . $db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS calculations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    current_salary REAL,
    level TEXT,
    inflasi REAL,
    adj REAL,
    gpms REAL,
    total_percentage REAL,
    increase_amount REAL,
    new_salary REAL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

try {
    $pdo->exec("ALTER TABLE calculations ADD COLUMN commuting_allowance REAL DEFAULT 0");
    $pdo->exec("ALTER TABLE calculations ADD COLUMN jabatan_allowance REAL DEFAULT 0");
    $pdo->exec("ALTER TABLE calculations ADD COLUMN jabatan TEXT DEFAULT ''");
    $pdo->exec("ALTER TABLE calculations ADD COLUMN prev_commuting REAL DEFAULT 0");
    $pdo->exec("ALTER TABLE calculations ADD COLUMN total_thp REAL DEFAULT 0");
    $pdo->exec("ALTER TABLE calculations ADD COLUMN gpms_factor TEXT DEFAULT ''");
    $pdo->exec("ALTER TABLE calculations ADD COLUMN pc_name TEXT DEFAULT ''");
} catch (PDOException $e) {
}

if (isset($_POST['action']) && $_POST['action'] === 'get_history') {
    header('Content-Type: application/json');
    if (($_POST['password'] ?? '') !== 'admin123') {
        http_response_code(403);
        echo json_encode(['error' => 'Password salah!']);
        exit;
    }
    $stmt = $pdo->query("SELECT * FROM calculations ORDER BY created_at DESC");
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $current_salary_str = $_POST['current_salary'] ?? '';
    // Extract only digits
    $current_salary = (float) preg_replace('/[^\d]/', '', $current_salary_str);

    $level = $_POST['level'] ?? '';
    $adj_input = isset($_POST['adj_value']) ? (float) $_POST['adj_value'] : 1.0;

    if ($current_salary > 0 && $level !== '') {
        $inflasi = 2.19;
        $adj = 0.0;
        
        $gpms = 0.0;
        $gpms_factor = $_POST['gpms_factor'] ?? '';

        if (in_array($level, ['CL23', 'CL22', 'CL21'])) {
            switch ($gpms_factor) {
                case 'AA': $gpms = 3.0; break;
                case 'AB': $gpms = 2.5; break;
                case 'BB': $gpms = 2.0; break;
                case 'BC': $gpms = 1.5; break;
                case 'CC': $gpms = 1.0; break;
                case 'CD_DD': $gpms = 0.0; break;
            }
        } elseif ($level === 'CL1.3') {
            switch ($gpms_factor) {
                case 'AA': $gpms = 1.0; break;
                case 'AB': $gpms = 0.5; break;
                case 'BB': $gpms = 0.5; break;
            }
        }

        $commuting_allowance = 0;

        switch ($level) {
            case 'CL23':
            case 'CL22':
            case 'CL21':
                $adj = 0.70;
                $commuting_allowance = 50000;
                break;
            case 'CL1.3':
                $adj = 1.50;
                $commuting_allowance = 30000;
                break;
            case 'CL1.2':
                $adj = 1.25;
                $commuting_allowance = 40000;
                break;
            case 'CL1.1':
                $adj = 1.00;
                $commuting_allowance = 20000;
                break;
        }

        $jabatan = $_POST['jabatan'] ?? '';
        $jabatan_allowance = 0;
        switch ($jabatan) {
            case 'Shift leader':
                $jabatan_allowance = 100000;
                break;
            case 'Sector leader':
            case 'Line captain':
                $jabatan_allowance = 50000;
                break;
            case 'Part Leader':
                $jabatan_allowance = 150000;
                break;
            case 'Group Leader':
                $jabatan_allowance = 250000;
                break;
        }

        $prev_commuting_str = $_POST['prev_commuting'] ?? '0';
        $prev_commuting = (float) preg_replace('/[^\d]/', '', $prev_commuting_str);

        $total_percentage = $inflasi + $adj + $gpms;
        $increase_base_salary = $current_salary * ($total_percentage / 100);
        $new_base_salary = $current_salary + $increase_base_salary;

        $total_allowance = $commuting_allowance + $jabatan_allowance;
        $new_commuting = $prev_commuting + $commuting_allowance;

        // Total THP
        $total_thp = $new_base_salary + $new_commuting + $jabatan_allowance;
        // Keep $increase_amount backwards compatible, or logical
        $increase_amount = $increase_base_salary + $total_allowance;
        $new_salary = $current_salary + $increase_amount; // Legacy for DB if needed

        $levelDisplayMap = [
            'CL23' => 'CL23',
            'CL22' => 'CL22',
            'CL21' => 'CL21',
            'CL1.3' => 'CL13',
            'CL1.2' => 'CL12',
            'CL1.1' => 'CL11'
        ];

        $final_level = $levelDisplayMap[$level] ?? $level;

        $result = [
            'current_salary' => $current_salary,
            'inflasi' => $inflasi,
            'adj' => $adj,
            'gpms' => $gpms,
            'total_percentage' => $total_percentage,
            'increase_base_salary' => $increase_base_salary,
            'new_base_salary' => $new_base_salary,
            'prev_commuting' => $prev_commuting,
            'commuting_allowance' => $commuting_allowance,
            'new_commuting' => $new_commuting,
            'jabatan_allowance' => $jabatan_allowance,
            'jabatan' => $jabatan,
            'increase_amount' => $increase_amount,
            'new_salary' => $new_salary,
            'total_thp' => $total_thp,
            'level' => $final_level
        ];

        $nama_pengguna = gethostbyaddr($_SERVER['REMOTE_ADDR']);
        $stmt = $pdo->prepare("INSERT INTO calculations (current_salary, level, inflasi, adj, gpms, total_percentage, increase_amount, new_salary, commuting_allowance, jabatan_allowance, jabatan, prev_commuting, total_thp, gpms_factor, pc_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $current_salary,
            $final_level,
            $inflasi,
            $adj,
            $gpms,
            $total_percentage,
            $increase_amount,
            $new_salary,
            $commuting_allowance,
            $jabatan_allowance,
            $jabatan,
            $prev_commuting,
            $total_thp,
            $gpms_factor,
            $nama_pengguna
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="http://107.102.39.55/gajian/">
<!--<meta property="og:title"       content="Kalkulator Uang Gaji Pokok">-->
<meta property="og:description" content="Hitung Gaji Mungilmu.">
<meta property="og:image" content="http://107.102.39.55/gajian/favicon.png">
<meta property="og:locale" content="id_ID">
<meta property="og:site_name" content="Naik Gaji Ga Sih">


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalkulator Kenaikan Gaji 2026</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        google: {
                            blue: '#0b57d0',
                            blueHover: '#0842a0',
                            bg: '#f8f9fa',
                            surface: '#ffffff',
                            border: '#747775',
                            borderFocus: '#0b57d0',
                            text: '#1f1f1f',
                            textSecondary: '#444746',
                            highlight: '#d3e3fd'
                        }
                    },
                    fontFamily: {
                        sans: ['Roboto', 'sans-serif'],
                    },
                    boxShadow: {
                        google: '0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15)',
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Canvas Confetti Library -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f4f9;
            color: #1f1f1f;
        }

        /* Hide number input arrows */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type=number] {
            -moz-appearance: textfield;
        }

        .fade-in {
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-xl bg-white rounded-2xl shadow-google p-8 fade-in">
        <div class="flex items-center justify-center mb-6">
            <!-- Google-like icon header -->
            <div class="bg-google-highlight text-google-blue p-3 rounded-full mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <div class="text-center mb-8">
            <h1 class="text-2xl font-medium text-google-text">Kalkulator Kenaikan Gaji 2026</h1>
            <p class="text-google-textSecondary mt-2">Simulasi penyesuaian gaji Anda berdasarkan pengumuman 2026.</p>
        </div>

        <form id="payrollForm" method="POST" action="">
            <div class="space-y-5 flex flex-col">

                <!-- Gaji Saat Ini -->
                <div class="flex flex-col relative group">
                    <label
                        class="text-sm font-medium text-google-textSecondary absolute -top-2 left-3 bg-white px-1 z-10 transition-all group-focus-within:text-google-blue"
                        for="current_salary">
                        Gaji Pokok Saat Ini
                    </label>
                    <input type="text" id="current_salary" name="current_salary" required
                        placeholder="Contoh: 10.000.000"
                        class="w-full px-4 py-3.5 border border-google-border rounded-md hover:border-[#1f1f1f] focus:outline-none focus:border-2 focus:border-google-blue focus:py-[13px] transition-colors"
                        onkeyup="this.value = formatRupiah(this.value, 'Rp ')"
                        value="<?= isset($_POST['current_salary']) ? htmlspecialchars($_POST['current_salary']) : '' ?>">
                </div>

                <!-- Prev Commuting Allowance -->
                <div class="flex flex-col relative group">
                    <label
                        class="text-sm font-medium text-google-textSecondary absolute -top-2 left-3 bg-white px-1 z-10 transition-all group-focus-within:text-google-blue"
                        for="prev_commuting">
                        Tunjangan Transportasi Sebelumnya
                    </label>
                    <input type="text" id="prev_commuting" name="prev_commuting" required
                        placeholder="Contoh: 500.000"
                        class="w-full px-4 py-3.5 border border-google-border rounded-md hover:border-[#1f1f1f] focus:outline-none focus:border-2 focus:border-google-blue focus:py-[13px] transition-colors"
                        onkeyup="this.value = formatRupiah(this.value, 'Rp ')"
                        value="<?= isset($_POST['prev_commuting']) ? htmlspecialchars($_POST['prev_commuting']) : '' ?>">
                </div>

                <!-- Level -->
                <div class="flex flex-col relative group">
                    <label
                        class="text-sm font-medium text-google-textSecondary absolute -top-2 left-3 bg-white px-1 z-10 transition-all group-focus-within:text-google-blue"
                        for="level">
                        Level / Golongan
                    </label>
                    <div class="relative">
                        <select id="level" name="level" required
                            class="w-full appearance-none bg-transparent border border-google-border px-4 py-3.5 pr-8 rounded-md hover:border-[#1f1f1f] focus:outline-none focus:border-2 focus:border-google-blue focus:py-[13px] transition-colors cursor-pointer text-google-text">
                            <option value="">Pilih Level / Golongan</option>
                            <option value="CL23" <?= (isset($_POST['level']) && $_POST['level'] == 'CL23') ? 'selected' : '' ?>>CL23</option>
                            <option value="CL22" <?= (isset($_POST['level']) && $_POST['level'] == 'CL22') ? 'selected' : '' ?>>CL22</option>
                            <option value="CL21" <?= (isset($_POST['level']) && $_POST['level'] == 'CL21') ? 'selected' : '' ?>>CL21</option>
                            <option value="CL1.3" <?= (isset($_POST['level']) && $_POST['level'] == 'CL1.3') ? 'selected' : '' ?>>CL13</option>
                            <option value="CL1.2" <?= (isset($_POST['level']) && $_POST['level'] == 'CL1.2') ? 'selected' : '' ?>>CL12</option>
                            <option value="CL1.1" <?= (isset($_POST['level']) && $_POST['level'] == 'CL1.1') ? 'selected' : '' ?>>CL11</option>
                        </select>
                        <div
                            class="pointer-events-none absolute w-8 bottom-0 top-0 right-0 flex items-center justify-center text-google-textSecondary">
                            <svg class="fill-current h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Tunjangan Jabatan -->
                <div class="flex flex-col relative group">
                    <label
                        class="text-sm font-medium text-google-textSecondary absolute -top-2 left-3 bg-white px-1 z-10 transition-all group-focus-within:text-google-blue"
                        for="jabatan">
                        Tunjangan Jabatan (Opsional)
                    </label>
                    <div class="relative">
                        <select id="jabatan" name="jabatan"
                            class="w-full appearance-none bg-transparent border border-google-border px-4 py-3.5 pr-8 rounded-md hover:border-[#1f1f1f] focus:outline-none focus:border-2 focus:border-google-blue focus:py-[13px] transition-colors cursor-pointer text-google-text">
                            <option value="">Tidak Punya Jabatan</option>
                            <option value="Shift leader" <?= (isset($_POST['jabatan']) && $_POST['jabatan'] == 'Shift leader') ? 'selected' : '' ?>>Shift leader (+100.000)</option>
                            <option value="Sector leader" <?= (isset($_POST['jabatan']) && $_POST['jabatan'] == 'Sector leader') ? 'selected' : '' ?>>Sector leader (+50.000)</option>
                            <option value="Line captain" <?= (isset($_POST['jabatan']) && $_POST['jabatan'] == 'Line captain') ? 'selected' : '' ?>>Line captain (+50.000)</option>
                            <option value="Part Leader" <?= (isset($_POST['jabatan']) && $_POST['jabatan'] == 'Part Leader') ? 'selected' : '' ?>>Part Leader (+150.000)</option>
                            <option value="Group Leader" <?= (isset($_POST['jabatan']) && $_POST['jabatan'] == 'Group Leader') ? 'selected' : '' ?>>Group Leader (+250.000)</option>
                        </select>
                        <div
                            class="pointer-events-none absolute w-8 bottom-0 top-0 right-0 flex items-center justify-center text-google-textSecondary">
                            <svg class="fill-current h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Adjustment (Dynamic) -->
                <div id="adj_container" class="flex flex-col relative group transition-all hidden">
                    <label
                        class="text-sm font-medium text-google-textSecondary absolute -top-2 left-3 bg-white px-1 z-10 transition-all group-focus-within:text-google-blue"
                        for="adj_value">
                        Adjustment (%)
                    </label>
                    <input type="number" step="0.01" min="1.0" max="1.5" id="adj_value" name="adj_value"
                        placeholder="Antara 1.0 sampai 1.5"
                        class="w-full px-4 py-3.5 border border-google-border rounded-md hover:border-[#1f1f1f] focus:outline-none focus:border-2 focus:border-google-blue focus:py-[13px] transition-colors"
                        value="<?= isset($_POST['adj_value']) ? htmlspecialchars($_POST['adj_value']) : '1.0' ?>">
                    <p class="text-xs text-google-textSecondary mt-1 ml-1">Range yang diizinkan: 1.0% - 1.5%</p>
                </div>

                <!-- GPMS Factor -->
                <div id="gpms_container" class="flex flex-col relative group transition-all hidden pt-2">
                    <label
                        class="text-sm font-medium text-google-textSecondary absolute -top-2 left-3 bg-white px-1 z-10 transition-all group-focus-within:text-google-blue"
                        for="gpms_factor">
                        GPMS Factor
                    </label>
                    <div class="relative">
                        <select id="gpms_factor" name="gpms_factor"
                            class="w-full appearance-none bg-transparent border border-google-border px-4 py-3.5 pr-8 rounded-md hover:border-[#1f1f1f] focus:outline-none focus:border-2 focus:border-google-blue focus:py-[13px] transition-colors cursor-pointer text-google-text">
                            <option value="">Tidak Punya GPMS (0%)</option>
                            <option value="AA" <?= (isset($_POST['gpms_factor']) && $_POST['gpms_factor'] == 'AA') ? 'selected' : '' ?>>AA</option>
                            <option value="AB" <?= (isset($_POST['gpms_factor']) && $_POST['gpms_factor'] == 'AB') ? 'selected' : '' ?>>AB</option>
                            <option value="BB" <?= (isset($_POST['gpms_factor']) && $_POST['gpms_factor'] == 'BB') ? 'selected' : '' ?>>BB</option>
                            <option value="BC" <?= (isset($_POST['gpms_factor']) && $_POST['gpms_factor'] == 'BC') ? 'selected' : '' ?>>BC</option>
                            <option value="CC" <?= (isset($_POST['gpms_factor']) && $_POST['gpms_factor'] == 'CC') ? 'selected' : '' ?>>CC</option>
                            <option value="CD_DD" <?= (isset($_POST['gpms_factor']) && $_POST['gpms_factor'] == 'CD_DD') ? 'selected' : '' ?>>CD / DD</option>
                        </select>
                        <div
                            class="pointer-events-none absolute w-8 bottom-0 top-0 right-0 flex items-center justify-center text-google-textSecondary">
                            <svg class="fill-current h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path
                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-2">
                    <button type="submit"
                        class="w-full bg-google-blue hover:bg-google-blueHover text-white font-medium py-3 px-6 rounded-full transition duration-300 shadow-sm flex justify-center items-center gap-2">
                        <span>Hitung Kenaikan</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </div>
        </form>

        <?php if ($result): ?>
            <div id="result_section" class="mt-10 border-t border-google-border/30 pt-8 fade-in">
                <h2 class="text-lg font-medium text-google-text mb-5 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-google-blue" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11 4a1 1 0 10-2 0v4a1 1 0 102 0V7zm-3 1a1 1 0 10-2 0v3a1 1 0 102 0V8zM8 9a1 1 0 00-2 0v2a1 1 0 102 0V9z"
                            clip-rule="evenodd" />
                    </svg>
                    Ringkasan Hasil
                </h2>

                <!-- Kenaikan Gaji Pokok Card -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 mb-4 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-google-blue"></div>
                    <h3 class="text-sm font-bold text-google-text mb-3">1. Gaji Pokok</h3>
                    <div class="grid grid-cols-2 gap-y-2 gap-x-4 text-[14px]">
                        <div class="text-google-textSecondary">Gaji Pokok Awal</div>
                        <div class="text-right font-medium">Rp <?= number_format($result['current_salary'], 0, ',', '.') ?></div>
                        
                        <div class="col-span-2 pt-2">
                            <div class="text-xs text-google-textSecondary font-medium mb-1">Rincian Persentase Kenaikan:</div>
                            <div class="bg-gray-50 rounded pl-3 py-2 pr-3 space-y-1 text-[13px]">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Inflasi</span>
                                    <span class="font-medium"><?= number_format($result['inflasi'], 2) ?>%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Penyesuaian</span>
                                    <span class="font-medium"><?= number_format($result['adj'], 2) ?>%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">GPMS Factor</span>
                                    <span class="font-medium"><?= number_format($result['gpms'], 2) ?>%</span>
                                </div>
                                <div class="border-t border-gray-200 my-1"></div>
                                <div class="flex justify-between text-google-blue font-bold">
                                    <span>Total Persentase</span>
                                    <span><?= number_format($result['total_percentage'], 2) ?>%</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-google-textSecondary mt-2">Nominal Kenaikan</div>
                        <div class="text-right font-medium text-[#137333] mt-2">+ Rp <?= number_format($result['increase_base_salary'], 0, ',', '.') ?></div>
                        
                        <div class="col-span-2 border-b border-gray-100 my-1"></div>
                        
                        <div class="text-google-textSecondary font-bold text-google-text">Gaji Pokok Baru</div>
                        <div class="text-right font-bold text-google-text">Rp <?= number_format($result['new_base_salary'], 0, ',', '.') ?></div>
                    </div>
                </div>

                <!-- Kenaikan Commuting Allowance Card -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 mb-4 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-[#fbbc04]"></div>
                    <h3 class="text-sm font-bold text-google-text mb-3">2. Tunjangan Transportasi</h3>
                    <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-[14px]">
                        <div class="text-google-textSecondary">Lama</div>
                        <div class="text-right font-medium">Rp <?= number_format($result['prev_commuting'], 0, ',', '.') ?></div>
                        
                        <div class="text-google-textSecondary">Kenaikan Sesuai Level (<?= htmlspecialchars($result['level']) ?>)</div>
                        <div class="text-right font-medium text-[#137333]">+ Rp <?= number_format($result['commuting_allowance'], 0, ',', '.') ?></div>
                        
                        <div class="col-span-2 border-b border-gray-100 my-1"></div>
                        
                        <div class="text-google-textSecondary font-bold text-google-text">Tunjangan Transportasi Baru</div>
                        <div class="text-right font-bold text-google-text">Rp <?= number_format($result['new_commuting'], 0, ',', '.') ?></div>
                    </div>
                </div>

                <?php if ($result['jabatan_allowance'] > 0): ?>
                <!-- Tunjangan Jabatan Card -->
                <div class="bg-white border border-gray-200 shadow-sm rounded-xl p-5 mb-6 relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-1 h-full bg-[#34a853]"></div>
                    <h3 class="text-sm font-bold text-google-text mb-3">3. Tunjangan Jabatan</h3>
                    <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-[14px]">
                        <div class="text-google-textSecondary">Jabatan (<?= htmlspecialchars($result['jabatan']) ?>)</div>
                        <div class="text-right font-bold text-[#137333]">+ Rp <?= number_format($result['jabatan_allowance'], 0, ',', '.') ?></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="mb-6"></div>
                <?php endif; ?>

                <!-- Take Home Pay Card -->
                <div class="bg-gradient-to-br from-[#f0f4f9] to-[#ffffff] border-2 border-[#d3e3fd] rounded-xl p-6 shadow-sm text-center transform transition-transform hover:-translate-y-1 hover:shadow-md">
                    <div class="text-sm font-bold text-google-textSecondary mb-2 uppercase tracking-wide">Total Take Home Pay (THP) Baru</div>
                    <div class="text-[34px] font-black text-google-blue tracking-tight leading-none mb-2">Rp <?= number_format($result['total_thp'], 0, ',', '.') ?></div>
                    <div class="text-xs text-google-textSecondary">(Gaji Pokok + Tunjangan Transportasi + Tunjangan Jabatan)</div>
                    <div class="mt-3 text-[11px] text-google-blue font-medium bg-blue-50 py-1 px-3 rounded-full inline-block">Berlaku mulai 01 Januari 2026</div>
                </div>

                <div class="mt-8">
                    <a href="?"
                        class="w-full flex justify-center items-center gap-2 bg-white hover:bg-gray-50 border border-gray-300 text-google-text font-medium py-3 px-6 rounded-full transition duration-300 shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-google-textSecondary" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>Kalkulasi Ulang</span>
                    </a>
                </div>

                <script>
                    // Show confetti unconditionally on result load for extra joy!
                    document.addEventListener("DOMContentLoaded", function () {
                        // Auto scroll to result
                        const resSection = document.getElementById('result_section');
                        if (resSection) {
                            setTimeout(() => {
                                resSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }, 100);
                        }

                        var end = Date.now() + (2.0 * 1000);
                        var colors = ['#4285F4', '#34A853', '#FBBC05', '#EA4335']; // Google colors

                        (function frame() {
                            confetti({
                                particleCount: 5,
                                angle: 60,
                                spread: 55,
                                origin: { x: 0 },
                                colors: colors
                            });
                            confetti({
                                particleCount: 5,
                                angle: 120,
                                spread: 55,
                                origin: { x: 1 },
                                colors: colors
                            });

                            if (Date.now() < end) {
                                requestAnimationFrame(frame);
                            }
                        }());
                    });
                </script>
            </div>
        <?php endif; ?>
    </div>

    <!-- Floating Gear Button -->
    <button
        onclick="document.getElementById('pwdModal').classList.remove('hidden'); document.getElementById('adminPwd').focus();"
        class="fixed bottom-6 right-6 bg-google-textSecondary hover:bg-google-text text-white p-4 rounded-full shadow-google transition-transform hover:rotate-90 z-40">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    </button>

    <!-- Password Modal -->
    <div id="pwdModal"
        class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center fade-in backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-96 p-6">
            <h3 class="text-lg font-medium text-google-text mb-4 text-center">Setting</h3>
            <p id="pwdError" class="text-[#d93025] bg-[#fad2cf] p-2 rounded text-sm hidden mb-3 text-center"></p>
            <input type="password" id="adminPwd" placeholder="Masukkan Password"
                onkeypress="if(event.key === 'Enter') fetchHistory()"
                class="w-full px-4 py-3 border border-google-border rounded-md hover:border-[#1f1f1f] focus:outline-none focus:border-2 focus:border-google-blue transition-colors mb-5 text-center tracking-widest">
            <div class="flex justify-end gap-3 w-full">
                <button onclick="document.getElementById('pwdModal').classList.add('hidden')"
                    class="w-1/2 py-2 text-google-textSecondary hover:bg-gray-100 rounded-full font-medium transition-colors">Batal</button>
                <button onclick="fetchHistory()"
                    class="w-1/2 py-2 bg-google-blue hover:bg-google-blueHover text-white rounded-full font-medium transition-colors">Masuk</button>
            </div>
        </div>
    </div>

    <!-- History Data Modal -->
    <div id="historyModal"
        class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center fade-in backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col">
            <div class="p-5 border-b border-gray-200 flex justify-between items-center bg-gray-50 rounded-t-2xl">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-google-textSecondary" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-lg font-medium text-google-text">History Perhitungan Gaji</h3>
                </div>
                <button onclick="document.getElementById('historyModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-google-text bg-white p-1 rounded-full shadow-sm hover:shadow transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>
            <div class="p-0 overflow-auto flex-1">
                <table class="w-full text-left text-sm text-google-text">
                    <thead class="text-xs text-google-textSecondary bg-gray-50 border-b sticky top-0">
                        <tr>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider whitespace-nowrap">Waktu (UTC)</th>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider whitespace-nowrap">Nama PC</th>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider whitespace-nowrap">Level</th>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider whitespace-nowrap">Inflasi</th>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider whitespace-nowrap">Adj.</th>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider whitespace-nowrap">GPMS</th>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider whitespace-nowrap">Jabatan</th>
                            
                            <th class="px-3 py-3 font-medium uppercase tracking-wider text-right whitespace-nowrap border-l border-gray-200">GP Lama</th>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider text-right whitespace-nowrap">Comm. Lama</th>
                            
                            <th class="px-3 py-3 font-medium uppercase tracking-wider text-right whitespace-nowrap border-l border-gray-200">GP Baru</th>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider text-right whitespace-nowrap">Comm. Baru</th>
                            
                            <th class="px-3 py-3 font-medium uppercase tracking-wider text-right whitespace-nowrap bg-blue-50 border-l border-gray-200">Total THP</th>
                            <th class="px-3 py-3 font-medium uppercase tracking-wider text-right whitespace-nowrap bg-green-50">GAP Kenaikan</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody" class="divide-y divide-gray-100">
                        <!-- Dynamic -->
                    </tbody>
                </table>
            </div>
            <div class="p-4 bg-gray-50 rounded-b-2xl border-t border-gray-200 text-right">
                <button onclick="document.getElementById('historyModal').classList.add('hidden')"
                    class="px-6 py-2 bg-google-textSecondary hover:bg-google-text text-white rounded-full font-medium transition-colors">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const levelSelect = document.getElementById('level');
            const adjContainer = document.getElementById('adj_container');
            const adjInput = document.getElementById('adj_value');

            const gpmsContainer = document.getElementById('gpms_container');

            function updateVisibility() {
                const val = levelSelect.value;
                if (false && (val === 'CL1.3' || val === 'CL1.2' || val === 'CL1.1')) {
                    adjContainer.classList.remove('hidden');
                    adjInput.setAttribute('required', 'required');
                } else {
                    adjContainer.classList.add('hidden');
                    adjInput.removeAttribute('required');
                }

                if (['CL23', 'CL22', 'CL21', 'CL1.3'].includes(val)) {
                    gpmsContainer.classList.remove('hidden');
                } else {
                    gpmsContainer.classList.add('hidden');
                    document.getElementById('gpms_factor').value = '';
                }
            }

            levelSelect.addEventListener('change', function () {
                updateVisibility();
            });
            updateVisibility();

            const form = document.getElementById('payrollForm');
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                // Fire a quick burst of confetti on click
                confetti({
                    particleCount: 120,
                    spread: 80,
                    origin: { y: 0.6 },
                    colors: ['#4285F4', '#34A853', '#FBBC05', '#EA4335'],
                    zIndex: 1000
                });

                // Wait briefly for the user to see the initial confetti splash, then submit
                setTimeout(() => {
                    HTMLFormElement.prototype.submit.call(form);
                }, 750);
            });
        });

        function formatRupiah(angka, prefix) {
            var number_string = angka.replace(/[^,\d]/g, '').toString(),
                split = number_string.split(','),
                sisa = split[0].length % 3,
                rupiah = split[0].substr(0, sisa),
                ribuan = split[0].substr(sisa).match(/\d{3}/gi);

            if (ribuan) {
                var separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }

            rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
            return prefix == undefined ? rupiah : (rupiah ? 'Rp ' + rupiah : '');
        }

        function fetchHistory() {
            const pwd = document.getElementById('adminPwd').value;
            const err = document.getElementById('pwdError');

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_history&password=' + encodeURIComponent(pwd)
            })
                .then(res => res.json().then(data => ({ status: res.status, body: data })))
                .then(obj => {
                    if (obj.status !== 200) {
                        err.textContent = obj.body.error || 'Terjadi kesalahan';
                        err.classList.remove('hidden');
                    } else {
                        err.classList.add('hidden');
                        document.getElementById('pwdModal').classList.add('hidden');
                        document.getElementById('adminPwd').value = '';

                        const tbody = document.getElementById('historyTableBody');
                        tbody.innerHTML = '';
                        if (obj.body.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="13" class="px-5 py-8 text-center text-gray-500">Belum ada history perhitungan</td></tr>';
                        } else {
                            obj.body.data.forEach(row => {
                                const tr = document.createElement('tr');
                                tr.className = 'hover:bg-[#f8f9fa] transition-colors';

                                const new_commuting = (parseFloat(row.prev_commuting) || 0) + (parseFloat(row.commuting_allowance) || 0);
                                const thp = row.total_thp && row.total_thp != 0 ? parseFloat(row.total_thp) : parseFloat(row.new_salary);

                                const gpmsText = row.gpms_factor ? `${row.gpms_factor} (${row.gpms}%)` : `${row.gpms}%`;
                                const jabatanText = row.jabatan && row.jabatan !== '' ? row.jabatan : '-';
                                
                                const oldTotal = (parseFloat(row.current_salary) || 0) + (parseFloat(row.prev_commuting) || 0);
                                const gap = thp - oldTotal;

                                tr.innerHTML = `
                                <td class="px-3 py-3 whitespace-nowrap text-google-textSecondary text-sm">${row.created_at}</td>
                                <td class="px-3 py-3 whitespace-nowrap text-gray-500 text-sm">${row.pc_name || '-'}</td>
                                <td class="px-3 py-3 font-medium text-sm whitespace-nowrap">${row.level}</td>
                                <td class="px-3 py-3 text-sm whitespace-nowrap">${row.inflasi}%</td>
                                <td class="px-3 py-3 text-sm whitespace-nowrap">${row.adj}%</td>
                                <td class="px-3 py-3 text-sm whitespace-nowrap">${gpmsText}</td>
                                <td class="px-3 py-3 text-sm whitespace-nowrap text-gray-600">${jabatanText}</td>
                                
                                <td class="px-3 py-3 text-right text-gray-500 line-through text-sm whitespace-nowrap border-l border-gray-100">Rp ${formatRupiah(row.current_salary.toString())}</td>
                                <td class="px-3 py-3 text-right text-gray-500 line-through text-sm whitespace-nowrap">Rp ${formatRupiah((row.prev_commuting || 0).toString())}</td>
                                
                                <td class="px-3 py-3 text-right text-sm whitespace-nowrap border-l border-gray-100">Rp ${formatRupiah(row.new_salary.toString())}</td>
                                <td class="px-3 py-3 text-right text-[#137333] text-sm whitespace-nowrap">Rp ${formatRupiah(new_commuting.toString())}</td>
                                
                                <td class="px-3 py-3 text-right font-bold text-google-blue text-sm whitespace-nowrap bg-blue-50/30 border-l border-gray-100">Rp ${formatRupiah(thp.toString())}</td>
                                <td class="px-3 py-3 text-right font-bold text-[#137333] text-sm whitespace-nowrap bg-green-50/30">+ Rp ${formatRupiah(gap.toString())}</td>
                            `;
                                tbody.appendChild(tr);
                            });
                        }

                        document.getElementById('historyModal').classList.remove('hidden');
                    }
                })
                .catch(e => {
                    err.textContent = 'Koneksi gagal / Error Jaringan';
                    err.classList.remove('hidden');
                });
        }
    </script>
</body>

</html>