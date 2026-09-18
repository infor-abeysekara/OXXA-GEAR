<?php
// OXXA GEAR - Sri Lanka Location Data API
// Returns districts by province, or cities by district
header('Content-Type: application/json');

$SL_DATA = [
    'Western' => [
        'Colombo' => ['Colombo 01','Colombo 02','Colombo 03','Colombo 04','Colombo 05','Colombo 06','Colombo 07','Colombo 08','Colombo 09','Colombo 10','Colombo 11','Colombo 12','Colombo 13','Colombo 14','Colombo 15','Dehiwala','Mount Lavinia','Moratuwa','Ratmalana','Kesbewa','Maharagama','Homagama','Padukka','Boralesgamuwa','Nugegoda','Kotte','Battaramulla','Rajagiriya','Kaduwela','Avissawella','Hanwella','Kolonnawa'],
        'Gampaha' => ['Gampaha','Negombo','Wattala','Kelaniya','Ragama','Ja-Ela','Katana','Minuwangoda','Veyangoda','Attanagalla','Divulapitiya','Mirigama','Nittambuwa','Ganemulla','Mahara','Biyagama','Kadawatha','Peliyagoda'],
        'Kalutara' => ['Kalutara','Panadura','Horana','Bandaragama','Beruwala','Aluthgama','Matugama','Agalawatta','Ingiriya','Dodangoda','Wadduwa','Bulathsinhala'],
    ],
    'Central' => [
        'Kandy' => ['Kandy','Peradeniya','Katugastota','Akurana','Gampola','Nawalapitiya','Pilimathalawa','Kundasale','Digana','Wattegama','Theldeniya','Hasalaka'],
        'Matale' => ['Matale','Dambulla','Sigiriya','Pallepola','Rattota','Ukuwela','Laggala','Galewela','Naula'],
        'Nuwara Eliya' => ['Nuwara Eliya','Hatton','Talawakele','Ginigathena','Ragala','Walapane','Maskeliya','Kotagala','Lindula'],
    ],
    'Southern' => [
        'Galle' => ['Galle','Hikkaduwa','Ambalangoda','Bentota','Balapitiya','Karandeniya','Elpitiya','Baddegama','Neluwa','Akmeemana'],
        'Matara' => ['Matara','Weligama','Mirissa','Dickwella','Akuressa','Deniyaya','Hakmana','Kamburupitiya','Malimbada','Kotapola'],
        'Hambantota' => ['Hambantota','Tangalle','Tissamaharama','Weeraketiya','Ambalantota','Beliatta','Kirinda','Sooriyawewa','Lunugamvehera'],
    ],
    'Northern' => [
        'Jaffna' => ['Jaffna','Chavakachcheri','Point Pedro','Nallur','Manipay','Kopay','Uduvil','Tellippalai','Kayts','Karainagar'],
        'Kilinochchi' => ['Kilinochchi','Paranthan','Poonakary','Kandavalai'],
        'Mannar' => ['Mannar','Murunkan','Nanattan','Madhu'],
        'Mullaitivu' => ['Mullaitivu','Puthukkudiyiruppu','Oddusuddan','Maritimepattu'],
        'Vavuniya' => ['Vavuniya','Cheddikulam','Vavuniya North','Vavuniya South'],
    ],
    'Eastern' => [
        'Ampara' => ['Ampara','Kalmunai','Akkaraipattu','Sammanthurai','Pottuvil','Mahaoya','Uhana','Damana'],
        'Batticaloa' => ['Batticaloa','Valaichenai','Eravur','Chenkaladi','Kattankudy','Oddamavadi'],
        'Trincomalee' => ['Trincomalee','Kinniya','Muttur','Morawewa','Thampalakamam','Kantale'],
    ],
    'North Western' => [
        'Kurunegala' => ['Kurunegala','Kuliyapitiya','Maho','Narammala','Pannala','Wariyapola','Giriulla','Alawwa','Nikaweratiya','Bingiriya','Ibbagamuwa'],
        'Puttalam' => ['Puttalam','Chilaw','Wennappuwa','Anamaduwa','Marawila','Bangadeniya','Mundalama','Dankotuwa','Nattandiya','Madampe'],
    ],
    'North Central' => [
        'Anuradhapura' => ['Anuradhapura','Kekirawa','Medawachchiya','Eppawala','Tambuttegama','Galnewa','Mihinthale','Nochchiyagama'],
        'Polonnaruwa' => ['Polonnaruwa','Kaduruwela','Hingurakgoda','Medirigiriya','Thamankaduwa','Lankapura'],
    ],
    'Uva' => [
        'Badulla' => ['Badulla','Bandarawela','Haputale','Welimada','Mahiyanganaya','Hali Ela','Passara','Lunugala','Ella','Diyatalawa'],
        'Monaragala' => ['Monaragala','Bibile','Wellawaya','Buttala','Kataragama','Siyambalanduwa','Thanamalvila'],
    ],
    'Sabaragamuwa' => [
        'Kegalle' => ['Kegalle','Mawanella','Rambukkana','Warakapola','Ruwanwella','Deraniyagala','Aranayake','Galigamuwa'],
        'Ratnapura' => ['Ratnapura','Balangoda','Embilipitiya','Kahawatta','Eheliyagoda','Kuruvita','Pelmadulla','Godakawela'],
    ],
];

$action = $_GET['action'] ?? '';

// Helper to normalize province lookup (handles "Western", "Western Province", lowercase, etc.)
function resolveProvinceKey($province, $data) {
    $raw = trim($province);
    if (!$raw) return null;
    if (isset($data[$raw])) return $raw;

    // Remove trailing " Province" if present
    $stripped = trim(preg_replace('/\s+Province$/i', '', $raw));
    if (isset($data[$stripped])) return $stripped;

    // Case-insensitive search
    foreach ($data as $key => $val) {
        if (strcasecmp($key, $raw) === 0 || strcasecmp($key, $stripped) === 0) {
            return $key;
        }
    }
    return null;
}

if ($action === 'districts') {
    $province = trim($_GET['province'] ?? '');
    $pKey = resolveProvinceKey($province, $SL_DATA);
    if ($pKey !== null && isset($SL_DATA[$pKey])) {
        echo json_encode(['success' => true, 'districts' => array_keys($SL_DATA[$pKey])]);
    } else {
        echo json_encode(['success' => false, 'districts' => []]);
    }

} elseif ($action === 'cities') {
    $province = trim($_GET['province'] ?? '');
    $district = trim($_GET['district'] ?? '');
    $pKey = resolveProvinceKey($province, $SL_DATA);
    
    // Direct match within province
    if ($pKey !== null && isset($SL_DATA[$pKey])) {
        foreach ($SL_DATA[$pKey] as $dName => $cities) {
            if (strcasecmp($dName, $district) === 0) {
                echo json_encode(['success' => true, 'cities' => $cities]);
                exit;
            }
        }
    }

    // Fallback search across all provinces for this district
    foreach ($SL_DATA as $provKey => $districts) {
        foreach ($districts as $dName => $cities) {
            if (strcasecmp($dName, $district) === 0) {
                echo json_encode(['success' => true, 'cities' => $cities]);
                exit;
            }
        }
    }

    echo json_encode(['success' => false, 'cities' => []]);

} elseif ($action === 'provinces') {
    echo json_encode(['success' => true, 'provinces' => array_keys($SL_DATA)]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
