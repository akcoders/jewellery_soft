<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require dirname(__DIR__) . '/vendor/autoload.php';

$vendors = [
    'ASHWA' => ['name' => 'ASHWA GEMS', 'address' => '106, B Wing, Sangeeta Apartments, Om Dutt Mandir CHS, Datt Mandir Road, Malad East, Mumbai, Maharashtra', 'gstin' => '27ADGPJ4969E1ZC', 'phone' => '', 'email' => 'ashwagems@rediffmail.com'],
    'BHAGWANDAS' => ['name' => "BHAGWANDAS & SON'S", 'address' => '33/37, Gems Niketan, Dhanji Street, Near Mumbadevi Temple, Mumbai - 400003, Maharashtra', 'gstin' => '27AAAFB1860H1ZR', 'phone' => '', 'email' => 'jewelers5@hotmail.com'],
    'DIAMONDS_ON_CALL' => ['name' => 'DIAMONDS ON CALL INDIA LLP', 'address' => 'GE9100, Bharat Diamond Bourse, Bandra Kurla Complex, Bandra East, Mumbai - 400051, Maharashtra', 'gstin' => '27AASFD2652D1ZF', 'phone' => '+91 63588 52170', 'email' => 'inquiry@diamondsoncall.com'],
    'KALASHA' => ['name' => 'KALASHA FINE JEWELS PRIVATE LIMITED', 'address' => '8-2-623/5/1/1, Avenue IV, Road No. 10, Banjara Hills, Hyderabad, Telangana', 'gstin' => '36AAMCK2854M1ZY', 'phone' => '', 'email' => ''],
    'KASHVI' => ['name' => 'KASHVI GEMS', 'address' => 'Sutaria Bhavan, 1st Floor, 79, Dhanji Street, Zaveri Bazar, Mumbai - 400003, Maharashtra', 'gstin' => '27AAQFK3745C1Z8', 'phone' => '022 23440187', 'email' => ''],
    'PARTH' => ['name' => 'PARTH GEMS', 'address' => 'B-173, Mahesh Nagar, Tonk Road, Jaipur, Rajasthan', 'gstin' => '08AGBPM3571F1ZI', 'phone' => '', 'email' => 'gemsdinesh11@gmail.com'],
    'RITESH' => ['name' => 'RITESH DIAMONDS', 'address' => '21-6-526, Ground Floor, Ghansi Bazar, Hyderabad - 500002, Telangana', 'gstin' => '36ADFPK3420L1ZM', 'phone' => '9246161621', 'email' => ''],
    'VEER' => ['name' => 'VEER DIAM', 'address' => 'EW 2080, Tower E, Bharat Diamond Bourse, Bandra Kurla Complex, Bandra East, Mumbai - 400051, Maharashtra', 'gstin' => '27AALFV4553C1Z2', 'phone' => '022 49738281 / 9920044836', 'email' => 'veerdiam123@gmail.com'],
    'CAPSGOLD_MH' => ['name' => 'CAPSGOLD PRIVATE LIMITED - MUMBAI', 'address' => '1st Floor, Office No. 13, Chawla Building, 111 Mumbadevi Road, Tambakata, Mumbai - 400002, Maharashtra', 'gstin' => '27AADCC6581E1ZN', 'phone' => '022-22400931', 'email' => 'backoffice@capsgold.com'],
    'CAPSGOLD_TS' => ['name' => 'CAPSGOLD PRIVATE LIMITED - TELANGANA', 'address' => '3-2-354, S.V. Street, R.P. Road, Secunderabad - 500003, Telangana', 'gstin' => '36AADCC6581E1ZO', 'phone' => '040-66332499', 'email' => 'backoffice@capsgold.com'],
    'LORVEN' => ['name' => 'LORVEN GOLD AND JEWELLERS LLP', 'address' => 'Door No. 20/1/02, 9th Main Road, Jayanagar 3rd Block, Bangalore South - 560011, Karnataka', 'gstin' => '29AAKFL1085C1ZC', 'phone' => '', 'email' => ''],
    'SMGJ_HYD' => ['name' => 'SRI MAHALAXMI GOLD AND JEWELLERS - HYDERABAD', 'address' => '8-2-623/5/1/1, Avenue 4, Road No. 10, Banjara Hills, Hyderabad, Telangana', 'gstin' => '36ADWFS0414R1ZA', 'phone' => '', 'email' => ''],
    'SMGJ_VJY' => ['name' => 'SRI MAHALAXMI GOLD AND JEWELLERS - VIJAYAWADA', 'address' => '40-15/2-2, Vyshali Towers, M.G. Road, Brindavan Colony, Labbipet, Vijayawada - 520010, Andhra Pradesh', 'gstin' => '37ADWFS0414R1Z8', 'phone' => '9121291934', 'email' => ''],
];

$purchases = [];
$addPurchase = static function (
    string $sourcePath,
    string $vendorKey,
    string $category,
    string $invoiceNo,
    string $invoiceDate,
    string $description,
    float $taxable,
    ?float $cgstRate,
    float $cgst,
    ?float $sgstRate,
    float $sgst,
    ?float $igstRate,
    float $igst,
    float $roundOff,
    float $total,
    string $paymentTerms = '',
    string $dueDate = '',
    string $placeOfSupply = 'Maharashtra',
    string $notes = '',
    string $supplierAddress = ''
) use (&$purchases): void {
    $isPaid = str_contains(strtoupper(basename($sourcePath)), 'PAID');
    $purchases[] = compact(
        'sourcePath', 'vendorKey', 'category', 'invoiceNo', 'invoiceDate', 'description', 'taxable',
        'cgstRate', 'cgst', 'sgstRate', 'sgst', 'igstRate', 'igst', 'roundOff', 'total',
        'paymentTerms', 'dueDate', 'placeOfSupply', 'notes', 'supplierAddress', 'isPaid'
    );
};

$diamond = 'anuj/prchase/diamond n cs/';
$gold = 'anuj/prchase/gold/';

$addPurchase($diamond . 'ASHWA GEMS/01.04.2026 PAID.pdf', 'ASHWA', 'Diamond', '01/2026-27', '2026-04-01', 'Lab Grown Diamonds', 239060.00, 0.75, 1792.95, 0.75, 1792.95, null, 0, 0.10, 242646.00, '0 days');
$addPurchase($diamond . 'ASHWA GEMS/04.04.2026 PAID.pdf', 'ASHWA', 'Diamond', '02/2026-27', '2026-04-04', 'Lab Grown Diamonds', 255870.00, 0.75, 1919.03, 0.75, 1919.03, null, 0, -0.06, 259708.00, '0 days');
$addPurchase($diamond . 'ASHWA GEMS/06.08.2026.pdf', 'ASHWA', 'Diamond', '28/2026_27', '2026-08-06', 'Cut & Polished Diamonds', 1299015.00, 0.75, 9742.61, 0.75, 9742.61, null, 0, 0.78, 1318501.00, '90 Days', '2026-11-04');
$addPurchase($diamond . 'ASHWA GEMS/10.06.2026.pdf', 'ASHWA', 'Diamond', '14/2026-27', '2026-06-10', 'Cut & Polished Diamonds', 1514100.00, 0.75, 11355.75, 0.75, 11355.75, null, 0, 0.50, 1536812.00, '90 Days', '2026-09-08');
$addPurchase($diamond . 'ASHWA GEMS/14.04.2026 PAID.pdf', 'ASHWA', 'Diamond', '03/2026-27', '2026-04-14', 'Lab Grown Diamonds', 379618.02, 0.75, 2847.14, 0.75, 2847.14, null, 0, -0.30, 385312.00, '0 days');
$addPurchase($diamond . 'BHAGWANDAS & SONS/14.04.2026 PAID.pdf', 'BHAGWANDAS', 'Stone', '73', '2026-04-14', 'Synthetic Stones', 16737.00, 0.125, 20.92, 0.125, 20.92, null, 0, 0.16, 16779.00);
$addPurchase($diamond . 'BHAGWANDAS & SONS/15.07.2026.pdf', 'BHAGWANDAS', 'Stone', '585', '2026-07-15', 'Semi Precious Stones', 34760.88, 0.125, 43.45, 0.125, 43.45, null, 0, 0.22, 34848.00);
$addPurchase($diamond . 'DIAMOND ON CALL/06.06.2026.pdf', 'DIAMONDS_ON_CALL', 'Diamond', 'DOC-01593', '2026-06-06', 'Cut & Polished Natural Diamonds', 196029.63, 0.75, 1470.22, 0.75, 1470.22, null, 0, 0, 198970.07, 'Advanced Payment', '2026-06-06');
$addPurchase($diamond . 'KALASHA/02.06.2026.pdf', 'KALASHA', 'Diamond', 'KFJ/26-27/109', '2026-06-02', 'Cut & Loose Polished Diamonds', 353597.00, null, 0, null, 0, 1.50, 5303.96, -0.96, 358900.00);
$addPurchase($diamond . 'KALASHA/03.07.2026.pdf', 'KALASHA', 'Diamond', 'KFJ/26-27/151', '2026-07-03', 'Cut & Loose Polished Diamonds', 112150.00, null, 0, null, 0, 1.50, 1682.25, -0.25, 113832.00);
$addPurchase($diamond . 'KALASHA/09.07.2026.pdf', 'KALASHA', 'Diamond', 'KFJ/26-27/156', '2026-07-09', 'Cut & Loose Polished Diamonds', 136400.00, null, 0, null, 0, 1.50, 2046.00, 0, 138446.00);
$addPurchase($diamond . 'KALASHA/20.04.2026.pdf', 'KALASHA', 'Diamond', 'KFJ/26-27/043', '2026-04-21', 'Cut & Loose Polished Diamonds', 246410.00, null, 0, null, 0, 1.50, 3696.00, 0, 250106.00, '', '', 'Maharashtra', 'PDF invoice date is 21-Apr-2026; filename is 20.04.2026.');
$addPurchase($diamond . 'KASHVI GEMS/15.07.2026.pdf', 'KASHVI', 'Stone', '56', '2026-07-15', 'Emerald Cut', 10517.00, null, 13.00, null, 13.00, null, 0, 0, 10543.00, '', '', 'Maharashtra', 'CGST and SGST amounts are handwritten as Rs 13 each; percentage cells are blank.');
$addPurchase($diamond . 'PARTH GEMS/07.04.2026 PAID.pdf', 'PARTH', 'Stone', '2026-2027/008', '2026-04-07', 'Precious Stone', 60168.00, null, 0, null, 0, 0.25, 150.42, -0.42, 60318.00);
$addPurchase($diamond . 'PARTH GEMS/10.07.2026.pdf', 'PARTH', 'Stone', '2026-2027/044', '2026-07-10', 'Precious Stone', 9350.00, null, 0, null, 0, 0.25, 23.38, -0.38, 9373.00);
$addPurchase($diamond . 'RITESH EXPORTS CS/BILL.pdf', 'RITESH', 'Stone', '658', '2026-06-09', 'Emerald Cut Stone', 25742.00, null, 0, null, 0, 0.25, 64.00, 0, 25806.00, '', '', 'Maharashtra', 'Handwritten invoice; date read as 09-Jun-2026.');
$addPurchase($diamond . 'VEER DIA/26.06.2026.pdf', 'VEER', 'Diamond', 'VD/L/071/2026-27', '2026-06-26', 'Cut & Polished Diamonds', 91800.00, 0.75, 688.50, 0.75, 688.50, null, 0, 0, 93177.00, '90 Days', '2026-09-24');

$addPurchase($gold . 'CAPSGOLD/01.07.2026.pdf', 'CAPSGOLD_MH', 'Gold', 'MH/2627/G/94', '2026-07-01', 'Pure Gold 999', 5732155.20, 1.50, 85982.33, 1.50, 85982.33, null, 0, 0.14, 5904120.00);
$addPurchase($gold . 'CAPSGOLD/04.05.2026.pdf', 'CAPSGOLD_MH', 'Gold', 'MH/2627/G/21', '2026-05-04', 'Pure Gold 999', 1491359.20, 1.50, 22370.39, 1.50, 22370.39, null, 0, 0.02, 1536100.00, '', '', 'Maharashtra', '', 'Shop No. 5, Ground Floor, Chawla Building, 111 Mumbadevi Road, Tambakata, Mumbai - 400002, Maharashtra');
$addPurchase($gold . 'CAPSGOLD/14.04.2026.pdf', 'CAPSGOLD_MH', 'Gold', 'MH/2627/G/3', '2026-04-14', 'Pure Gold 999', 1453592.20, 1.50, 21803.88, 1.50, 21803.88, null, 0, 0.04, 1497200.00, '', '', 'Maharashtra', '', 'Shop No. 5, Ground Floor, Chawla Building, 111 Mumbadevi Road, Tambakata, Mumbai - 400002, Maharashtra');
$addPurchase($gold . 'CAPSGOLD/20.05.2026.pdf', 'CAPSGOLD_TS', 'Gold', 'TG/2627/G/404', '2026-05-20', 'GoldHyd999', 1581650.50, null, 0, null, 0, 3.00, 47449.52, -0.02, 1629100.00);
$addPurchase($gold . 'CAPSGOLD/23.07.2026.pdf', 'CAPSGOLD_TS', 'Gold', 'TG/2627/G/790', '2026-07-23', 'Pure Gold', 1433038.80, null, 0, null, 0, 3.00, 42991.16, 0.04, 1476030.00);
$addPurchase($gold . 'KALASHA/02.06.2026 DIA.pdf', 'KALASHA', 'Gold', 'KFJ/26-27/108', '2026-06-02', 'Pure Gold', 614369.00, null, 0, null, 0, 3.00, 18431.07, -0.07, 632800.00);
$addPurchase($gold . 'KALASHA/02.06.2026 GOLD.pdf', 'KALASHA', 'Gold', 'KFJ/26-27/110', '2026-06-02', 'Pure Gold', 164758.00, null, 0, null, 0, 3.00, 4942.74, -0.74, 169700.00);
$addPurchase($gold . 'KALASHA/19.08.2026 GOLD.pdf', 'KALASHA', 'Gold', 'KFJ/26-27/228', '2026-08-19', 'Pure Gold', 299029.00, null, 0, null, 0, 3.00, 8970.87, 0.13, 308000.00);
$addPurchase($gold . 'LORVEN/17.08.2026.pdf', 'LORVEN', 'Gold', 'GST/26-27/122', '2026-08-17', 'Pure Gold', 297421.86, null, 0, null, 0, 3.00, 8922.66, 0.48, 306345.00);
$addPurchase($gold . 'LORVEN/31.07.2026.pdf', 'LORVEN', 'Gold', 'GST/26-27/106', '2026-07-31', 'Pure Gold', 189140.44, null, 0, null, 0, 3.00, 5674.21, 0.35, 194815.00);
$addPurchase($gold . 'SMGJ HYD/02.04.2026.pdf', 'SMGJ_HYD', 'Gold', 'SMGJ/HYD/BS/001', '2026-04-02', 'Pure Gold', 810542.72, null, 0, null, 0, 3.00, 24316.28, 0, 834859.00);
$addPurchase($gold . 'SMGJ HYD/02.06.2026.pdf', 'SMGJ_HYD', 'Gold', 'SMGJ/HYD/BS/018', '2026-06-02', 'Pure Gold', 3050096.97, null, 0, null, 0, 3.00, 91502.91, 0.12, 3141600.00);
$addPurchase($gold . 'SMGJ HYD/16.05.2026.pdf', 'SMGJ_HYD', 'Gold', 'SMGJ/HYD/BS/015', '2026-05-16', 'Pure Gold', 1157327.36, null, 0, null, 0, 3.00, 34719.82, -0.18, 1192047.00);
$addPurchase($gold . 'SMGJ HYD/19.08.2026.pdf', 'SMGJ_HYD', 'Gold', 'SMGJ/HYD/BS/046', '2026-08-19', 'Pure Gold', 415532.00, null, 0, null, 0, 3.00, 12465.96, 0.04, 427998.00);
$addPurchase($gold . 'SMGJ HYD/21.07.2026.pdf', 'SMGJ_HYD', 'Gold', 'SMGJ/HYD/BS/041', '2026-07-21', 'Pure Gold', 1632909.70, null, 0, null, 0, 3.00, 48987.29, 0.01, 1681897.00);
$addPurchase($gold . 'SMGJ VJY/09.04.2026.pdf', 'SMGJ_VJY', 'Gold', 'VIJ/002/26-27', '2026-04-09', 'Pure Gold', 1479515.00, null, 0, null, 0, 3.00, 44385.45, -0.45, 1523900.00);
$addPurchase($gold . 'SMGJ VJY/11.08.2026.pdf', 'SMGJ_VJY', 'Gold', 'VIJ/027/26-27', '2026-08-11', 'Pure Gold', 1461456.00, null, 0, null, 0, 3.00, 43843.68, 0.32, 1505300.00);
$addPurchase($gold . 'SMGJ VJY/18.04.2026.pdf', 'SMGJ_VJY', 'Gold', 'VIJ/005/26-27', '2026-04-18', 'Pure Gold', 1489320.39, null, 0, null, 0, 3.00, 44679.61, 0, 1534000.00);

$lines = [];
$addLine = static function (string $sourcePath, string $description, string $hsn, float $quantity, string $unit, float $rate, float $amount) use (&$lines): void {
    $lines[] = compact('sourcePath', 'description', 'hsn', 'quantity', 'unit', 'rate', 'amount');
};

$addLine($diamond . 'ASHWA GEMS/01.04.2026 PAID.pdf', 'Lab Grown Diamonds', '71023910', 1.000, 'cts', 11000.00, 11000.00);
$addLine($diamond . 'ASHWA GEMS/01.04.2026 PAID.pdf', 'Lab Grown Diamonds', '710239100', 25.340, 'cts', 9000.00, 228060.00);
$addLine($diamond . 'ASHWA GEMS/04.04.2026 PAID.pdf', 'Lab Grown Diamonds', '710239100', 28.430, 'cts', 9000.00, 255870.00);
$addLine($diamond . 'ASHWA GEMS/06.08.2026.pdf', 'Cut & Polished Diamonds', '71023910', 36.090, 'cts', 33500.00, 1209015.00);
$addLine($diamond . 'ASHWA GEMS/06.08.2026.pdf', 'Cut & Polished Diamonds', '71023910', 2.400, 'cts', 37500.00, 90000.00);
$addLine($diamond . 'ASHWA GEMS/10.06.2026.pdf', 'Cut & Polished Diamonds', '71023910', 43.260, 'cts', 35000.00, 1514100.00);
$addLine($diamond . 'ASHWA GEMS/14.04.2026 PAID.pdf', 'Lab Grown Diamonds', '710239100', 22.130, 'cts', 17154.00, 379618.02);
$addLine($diamond . 'BHAGWANDAS & SONS/14.04.2026 PAID.pdf', 'Synthetic Stones', '7104', 184.560, 'CART', 90.69, 16737.00);
$addLine($diamond . 'BHAGWANDAS & SONS/15.07.2026.pdf', 'Semi Precious Stones', '7103', 228.690, 'CART', 152.00, 34760.88);
$addLine($diamond . 'DIAMOND ON CALL/06.06.2026.pdf', 'Cut & Polished Natural Diamonds', '71023910', 0.900, 'Ct', 217810.70, 196029.63);
$addLine($diamond . 'DIAMOND ON CALL/06.06.2026.pdf', 'Convenience Fee', '999799', 1, 'service', 0, 0);
$addLine($diamond . 'KALASHA/02.06.2026.pdf', 'Cut & Loose Polished Diamonds', '710239', 8.840, 'CTS', 39999.66, 353597.00);
$addLine($diamond . 'KALASHA/03.07.2026.pdf', 'Cut & Loose Polished Diamonds', '710239', 2.610, 'CTS', 42969.35, 112150.00);
$addLine($diamond . 'KALASHA/09.07.2026.pdf', 'Cut & Loose Polished Diamonds', '710239', 3.410, 'CTS', 40000.00, 136400.00);
$addLine($diamond . 'KALASHA/20.04.2026.pdf', 'Cut & Loose Polished Diamonds', '710239', 18.280, 'CTS', 13479.76, 246410.00);
$addLine($diamond . 'KASHVI GEMS/15.07.2026.pdf', 'Emerald Cut', '7103', 6.010, 'cts', 1750.00, 10517.00);
$addLine($diamond . 'PARTH GEMS/07.04.2026 PAID.pdf', 'Precious Stone', '7103', 140.970, 'cts', 426.81, 60168.00);
$addLine($diamond . 'PARTH GEMS/10.07.2026.pdf', 'Precious Stone', '7103', 110.000, 'cts', 85.00, 9350.00);
$addLine($diamond . 'RITESH EXPORTS CS/BILL.pdf', 'Emerald Cut Stone', '7103', 136.200, 'cts', 189.00, 25742.00);
$addLine($diamond . 'VEER DIA/26.06.2026.pdf', 'Cut & Polished Diamonds', '71023910', 2.700, 'Carat', 34000.00, 91800.00);

$goldLines = [
    ['CAPSGOLD/01.07.2026.pdf', 'Pure Gold 999', '71081200', 400.000, 14330.388, 5732155.20],
    ['CAPSGOLD/04.05.2026.pdf', 'Pure Gold 999', '71081200', 100.000, 14913.592, 1491359.20],
    ['CAPSGOLD/14.04.2026.pdf', 'Pure Gold 999', '71081200', 100.000, 14535.922, 1453592.20],
    ['CAPSGOLD/20.05.2026.pdf', 'GoldHyd999', '71081300', 100.000, 15816.51, 1581650.50],
    ['CAPSGOLD/23.07.2026.pdf', 'Pure Gold', '71081300', 100.000, 14330.388, 1433038.80],
    ['KALASHA/02.06.2026 DIA.pdf', 'Pure Gold', '71081200', 39.550, 15533.98, 614369.00],
    ['KALASHA/02.06.2026 GOLD.pdf', 'Pure Gold', '71081200', 11.164, 14757.97, 164758.00],
    ['KALASHA/19.08.2026 GOLD.pdf', 'Pure Gold', '71081200', 20.000, 14951.45, 299029.00],
    ['LORVEN/17.08.2026.pdf', 'Pure Gold', '71081200', 11.727, 14271.85, 167365.98],
    ['LORVEN/17.08.2026.pdf', 'Pure Gold', '71081200', 8.425, 15436.90, 130055.88],
    ['LORVEN/31.07.2026.pdf', 'Pure Gold', '71081200', 13.250, 14274.75, 189140.44],
    ['SMGJ HYD/02.04.2026.pdf', 'Pure Gold', '71081200', 55.296, 14658.25, 810542.72],
    ['SMGJ HYD/02.06.2026.pdf', 'Pure Gold', '71081200', 196.350, 15533.98, 3050096.97],
    ['SMGJ HYD/16.05.2026.pdf', 'Pure Gold', '71081200', 73.360, 15776.00, 1157327.36],
    ['SMGJ HYD/19.08.2026.pdf', 'Pure Gold', '71081200', 26.986, 15398.06, 415532.00],
    ['SMGJ HYD/21.07.2026.pdf', 'Pure Gold', '71081200', 113.834, 14344.66, 1632909.70],
    ['SMGJ VJY/09.04.2026.pdf', 'Pure Gold', '71081300', 100.000, 14795.15, 1479515.00],
    ['SMGJ VJY/11.08.2026.pdf', 'Pure Gold', '71081300', 100.000, 14614.56, 1461456.00],
    ['SMGJ VJY/18.04.2026.pdf', 'Pure Gold', '71081300', 100.000, 14893.20, 1489320.39],
];
foreach ($goldLines as [$path, $description, $hsn, $quantity, $rate, $amount]) {
    $addLine($gold . $path, $description, $hsn, $quantity, 'GMS', $rate, $amount);
}

if (count($purchases) !== 35 || count($lines) !== 39) {
    throw new RuntimeException('Expected 35 purchase invoices and 39 line items.');
}

$purchasePaths = array_column($purchases, 'sourcePath');
if (count(array_unique($purchasePaths)) !== count($purchasePaths)) {
    throw new RuntimeException('Duplicate source path in purchase register.');
}
foreach ($purchases as $purchase) {
    $calculated = round($purchase['taxable'] + $purchase['cgst'] + $purchase['sgst'] + $purchase['igst'] + $purchase['roundOff'], 2);
    if (abs($calculated - $purchase['total']) > 0.011) {
        throw new RuntimeException('Invoice total mismatch for ' . $purchase['sourcePath'] . ': ' . $calculated . ' vs ' . $purchase['total']);
    }
    if (! isset($vendors[$purchase['vendorKey']])) {
        throw new RuntimeException('Unknown vendor key ' . $purchase['vendorKey']);
    }
}

$book = new Spreadsheet();
$book->getProperties()
    ->setCreator('Jewellery Soft production data review')
    ->setTitle('Verified Production Purchase Register 2026-27')
    ->setDescription('Manually analysed from the 35 source PDF invoices; no runtime PDF parsing.');

$purchaseHeaders = [
    'Source PDF', 'Category', 'Vendor Key', 'Vendor Name', 'Vendor Address', 'Vendor GSTIN', 'Vendor Phone', 'Vendor Email',
    'Invoice No', 'Invoice Date', 'Payment Terms', 'Due Date', 'Place of Supply', 'Description', 'Taxable Amount',
    'CGST Rate %', 'CGST Amount', 'SGST Rate %', 'SGST Amount', 'IGST Rate %', 'IGST Amount', 'GST Total',
    'Round Off', 'Invoice Total', 'Payment Status', 'Paid Amount', 'Payment Date', 'Verification', 'Notes',
];
$sheet = $book->getActiveSheet()->setTitle('Purchases');
$sheet->fromArray($purchaseHeaders, null, 'A1');
foreach ($purchases as $index => $purchase) {
    $vendor = $vendors[$purchase['vendorKey']];
    $sheet->fromArray([
        $purchase['sourcePath'], $purchase['category'], $purchase['vendorKey'], $vendor['name'],
        $purchase['supplierAddress'] ?: $vendor['address'], $vendor['gstin'], $vendor['phone'], $vendor['email'],
        $purchase['invoiceNo'], $purchase['invoiceDate'], $purchase['paymentTerms'], $purchase['dueDate'], $purchase['placeOfSupply'],
        $purchase['description'], $purchase['taxable'], $purchase['cgstRate'], $purchase['cgst'], $purchase['sgstRate'],
        $purchase['sgst'], $purchase['igstRate'], $purchase['igst'], $purchase['cgst'] + $purchase['sgst'] + $purchase['igst'],
        $purchase['roundOff'], $purchase['total'], $purchase['isPaid'] ? 'Paid' : 'Pending',
        $purchase['isPaid'] ? $purchase['total'] : 0, '', 'Verified from source PDF', $purchase['notes'],
    ], null, 'A' . ($index + 2));
}

$lineHeaders = ['Source PDF', 'Invoice No', 'Line No', 'Description', 'HSN/SAC', 'Quantity', 'Unit', 'Rate', 'Line Amount'];
$lineSheet = $book->createSheet()->setTitle('Line Items');
$lineSheet->fromArray($lineHeaders, null, 'A1');
$invoiceByPath = array_column($purchases, 'invoiceNo', 'sourcePath');
$lineNoByPath = [];
foreach ($lines as $index => $line) {
    $lineNoByPath[$line['sourcePath']] = ($lineNoByPath[$line['sourcePath']] ?? 0) + 1;
    $lineSheet->fromArray([
        $line['sourcePath'], $invoiceByPath[$line['sourcePath']] ?? '', $lineNoByPath[$line['sourcePath']],
        $line['description'], $line['hsn'], $line['quantity'], $line['unit'], $line['rate'], $line['amount'],
    ], null, 'A' . ($index + 2));
}

$vendorHeaders = ['Vendor Key', 'Vendor Name', 'Address', 'GSTIN', 'Phone', 'Email'];
$vendorSheet = $book->createSheet()->setTitle('Vendors');
$vendorSheet->fromArray($vendorHeaders, null, 'A1');
$vendorRow = 2;
foreach ($vendors as $key => $vendor) {
    $vendorSheet->fromArray([$key, $vendor['name'], $vendor['address'], $vendor['gstin'], $vendor['phone'], $vendor['email']], null, 'A' . $vendorRow++);
}

$auditSheet = $book->createSheet()->setTitle('Audit');
$auditSheet->fromArray([
    ['Register', 'Production purchases 2026-27'],
    ['Source', '35 supplied PDF invoices in anuj/prchase'],
    ['Method', 'PDFs manually analysed; this Excel workbook is the import source. The application does not parse PDFs.'],
    ['Invoices', count($purchases)],
    ['Line items', count($lines)],
    ['Paid invoices', count(array_filter($purchases, static fn(array $row): bool => $row['isPaid']))],
    ['Pending invoices', count(array_filter($purchases, static fn(array $row): bool => ! $row['isPaid']))],
    ['Total taxable', array_sum(array_column($purchases, 'taxable'))],
    ['Total GST', array_sum(array_column($purchases, 'cgst')) + array_sum(array_column($purchases, 'sgst')) + array_sum(array_column($purchases, 'igst'))],
    ['Total invoices', array_sum(array_column($purchases, 'total'))],
    ['Paid total', array_sum(array_map(static fn(array $row): float => $row['isPaid'] ? $row['total'] : 0, $purchases))],
    ['Payment rule', 'Only a source PDF filename containing PAID is treated as paid.'],
], null, 'A1');

foreach ([$sheet, $lineSheet, $vendorSheet] as $styledSheet) {
    $highestColumn = $styledSheet->getHighestColumn();
    $highestRow = $styledSheet->getHighestRow();
    $styledSheet->freezePane('A2');
    $styledSheet->setAutoFilter('A1:' . $highestColumn . $highestRow);
    $styledSheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $styledSheet->getStyle('A1:' . $highestColumn . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4F46E5');
    $styledSheet->getStyle('A1:' . $highestColumn . $highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    for ($column = 1; $column <= Coordinate::columnIndexFromString($highestColumn); $column++) {
        $styledSheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
    }
}
$sheet->getStyle('O2:X' . $sheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00');
$lineSheet->getStyle('F2:I' . $lineSheet->getHighestRow())->getNumberFormat()->setFormatCode('#,##0.000;[Red]-#,##0.000');
$auditSheet->getColumnDimension('A')->setWidth(24);
$auditSheet->getColumnDimension('B')->setWidth(100);
$auditSheet->getStyle('A1:A' . $auditSheet->getHighestRow())->getFont()->setBold(true);
$auditSheet->getStyle('A1:B' . $auditSheet->getHighestRow())->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

$output = dirname(__DIR__) . '/app/Database/Data/production_purchase_register.xlsx';
(new Xlsx($book))->save($output);
echo $output . PHP_EOL;
