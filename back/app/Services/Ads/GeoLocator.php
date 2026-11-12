<?php

namespace App\Services\Ads;

/**
 * Turns the location names Meta/Google report (country code + English region/city names)
 * into a map point. Coordinates are approximate centres, good enough for a dot on the
 * dashboard map, not for distance maths.
 */
class GeoLocator
{
    public const HOME_COUNTRY = 'ID';

    /** Province key => [Indonesian name, lat, lng]. Keys are normalize()d English names. */
    private const PROVINCES = [
        'aceh' => ['Aceh', 4.695, 96.749],
        'northsumatra' => ['Sumatera Utara', 2.115, 99.545],
        'westsumatra' => ['Sumatera Barat', -0.740, 100.800],
        'riau' => ['Riau', 0.293, 101.707],
        'riauislands' => ['Kepulauan Riau', 0.918, 104.450],
        'jambi' => ['Jambi', -1.610, 103.613],
        'southsumatra' => ['Sumatera Selatan', -3.319, 103.914],
        'bengkulu' => ['Bengkulu', -3.578, 102.346],
        'lampung' => ['Lampung', -4.559, 105.407],
        'bangkabelitungislands' => ['Kepulauan Bangka Belitung', -2.741, 106.441],
        'jakarta' => ['DKI Jakarta', -6.209, 106.846],
        'banten' => ['Banten', -6.406, 106.064],
        'westjava' => ['Jawa Barat', -6.915, 107.610],
        'centraljava' => ['Jawa Tengah', -7.151, 110.140],
        'yogyakarta' => ['DI Yogyakarta', -7.796, 110.370],
        'eastjava' => ['Jawa Timur', -7.536, 112.238],
        'bali' => ['Bali', -8.410, 115.189],
        'westnusatenggara' => ['Nusa Tenggara Barat', -8.653, 117.362],
        'eastnusatenggara' => ['Nusa Tenggara Timur', -8.657, 121.079],
        'westkalimantan' => ['Kalimantan Barat', -0.279, 111.475],
        'centralkalimantan' => ['Kalimantan Tengah', -1.682, 113.382],
        'southkalimantan' => ['Kalimantan Selatan', -3.093, 115.284],
        'eastkalimantan' => ['Kalimantan Timur', 0.539, 116.419],
        'northkalimantan' => ['Kalimantan Utara', 3.073, 116.041],
        'northsulawesi' => ['Sulawesi Utara', 0.625, 123.975],
        'gorontalo' => ['Gorontalo', 0.700, 122.447],
        'centralsulawesi' => ['Sulawesi Tengah', -1.430, 121.446],
        'westsulawesi' => ['Sulawesi Barat', -2.844, 119.232],
        'southsulawesi' => ['Sulawesi Selatan', -3.669, 119.974],
        'southeastsulawesi' => ['Sulawesi Tenggara', -4.145, 122.175],
        'maluku' => ['Maluku', -3.239, 130.145],
        'northmaluku' => ['Maluku Utara', 1.571, 127.809],
        'westpapua' => ['Papua Barat', -1.336, 133.175],
        'southwestpapua' => ['Papua Barat Daya', -0.880, 131.250],
        'papua' => ['Papua', -2.600, 139.000],
        'centralpapua' => ['Papua Tengah', -3.700, 136.500],
        'highlandpapua' => ['Papua Pegunungan', -4.100, 138.900],
        'southpapua' => ['Papua Selatan', -6.900, 139.500],
    ];

    /** Other spellings the platforms use => PROVINCES key. */
    private const PROVINCE_ALIASES = [
        'bangkabelitung' => 'bangkabelitungislands',
        'nanggroeacehdarussalam' => 'aceh',
        'diyogyakarta' => 'yogyakarta',
        'papuahighlands' => 'highlandpapua',
        'mountainpapua' => 'highlandpapua',
    ];

    /**
     * City/regency/village points inside a province, keyed by province key then normalize()d name.
     * Only Bali for now (where the clients are); other Indonesian cities fall back to their province.
     * Google reports villages (desa/kelurahan) with a flat "Village,Bali,Indonesia" name, so the
     * villages that actually get spend are listed one by one; the rest fall back to the province.
     */
    private const PLACES = [
        'bali' => [
            // Regencies, towns and well-known areas
            'denpasar' => ['Denpasar', -8.670, 115.213],
            'badung' => ['Kab. Badung', -8.585, 115.177],
            'gianyar' => ['Gianyar', -8.544, 115.325],
            'tabanan' => ['Tabanan', -8.539, 115.125],
            'bangli' => ['Bangli', -8.454, 115.355],
            'buleleng' => ['Kab. Buleleng', -8.200, 114.980],
            'jembrana' => ['Kab. Jembrana', -8.330, 114.620],
            'karangasem' => ['Kab. Karangasem', -8.380, 115.530],
            'klungkung' => ['Klungkung', -8.535, 115.403],
            'kuta' => ['Kuta', -8.718, 115.169],
            'northkuta' => ['Kuta Utara', -8.640, 115.160],
            'kutautara' => ['Kuta Utara', -8.640, 115.160],
            'southkuta' => ['Kuta Selatan', -8.800, 115.170],
            'kutaselatan' => ['Kuta Selatan', -8.800, 115.170],
            'legian' => ['Legian', -8.706, 115.169],
            'seminyak' => ['Seminyak', -8.691, 115.168],
            'kerobokan' => ['Kerobokan', -8.656, 115.165],
            'canggu' => ['Canggu', -8.648, 115.139],
            'dalung' => ['Dalung', -8.615, 115.182],
            'mengwi' => ['Mengwi', -8.543, 115.175],
            'abiansemal' => ['Abiansemal', -8.530, 115.220],
            'petang' => ['Petang', -8.380, 115.220],
            'jimbaran' => ['Jimbaran', -8.791, 115.160],
            'nusadua' => ['Nusa Dua', -8.800, 115.230],
            'ungasan' => ['Ungasan', -8.827, 115.163],
            'pecatu' => ['Pecatu', -8.824, 115.118],
            'tuban' => ['Tuban', -8.745, 115.170],
            'sanur' => ['Sanur', -8.688, 115.261],
            'ubud' => ['Ubud', -8.507, 115.263],
            'sukawati' => ['Sukawati', -8.597, 115.283],
            'tegallalang' => ['Tegallalang', -8.439, 115.279],
            'kediri' => ['Kediri', -8.590, 115.130],
            'singaraja' => ['Singaraja', -8.112, 115.088],
            'negara' => ['Negara', -8.358, 114.620],
            'amlapura' => ['Amlapura', -8.450, 115.608],
            'semarapura' => ['Semarapura', -8.535, 115.403],
            'nusapenida' => ['Nusa Penida', -8.730, 115.540],
            'benoa' => ['Benoa', -8.778, 115.215],
            'kutuh' => ['Kutuh', -8.826, 115.195],
            // Badung villages
            'tibubeneng' => ['Tibubeneng', -8.646, 115.143],
            'pererenan' => ['Pererenan', -8.637, 115.127],
            'kerobokankelod' => ['Kerobokan Kelod', -8.672, 115.163],
            'kerobokankaja' => ['Kerobokan Kaja', -8.648, 115.172],
            'cemagi' => ['Cemagi', -8.628, 115.101],
            'kapal' => ['Kapal', -8.590, 115.169],
            'lukluk' => ['Lukluk', -8.595, 115.180],
            'sibangkaja' => ['Sibang Kaja', -8.560, 115.205],
            // Denpasar districts and kelurahan
            'denpasarutara' => ['Denpasar Utara', -8.625, 115.215],
            'northdenpasar' => ['Denpasar Utara', -8.625, 115.215],
            'denpasarbarat' => ['Denpasar Barat', -8.660, 115.195],
            'westdenpasar' => ['Denpasar Barat', -8.660, 115.195],
            'denpasarselatan' => ['Denpasar Selatan', -8.700, 115.230],
            'southdenpasar' => ['Denpasar Selatan', -8.700, 115.230],
            'denpasartimur' => ['Denpasar Timur', -8.645, 115.245],
            'eastdenpasar' => ['Denpasar Timur', -8.645, 115.245],
            'renon' => ['Renon', -8.675, 115.235],
            'dauhpuriklod' => ['Dauh Puri Klod', -8.668, 115.210],
            'padangsambian' => ['Padangsambian', -8.655, 115.190],
            'padangsambianklod' => ['Padangsambian Klod', -8.665, 115.190],
            'padangsambiankaja' => ['Padangsambian Kaja', -8.640, 115.188],
            'sanurkauh' => ['Sanur Kauh', -8.695, 115.255],
            'panjer' => ['Panjer', -8.680, 115.225],
            'sidakarya' => ['Sidakarya', -8.695, 115.235],
            'pemogan' => ['Pemogan', -8.720, 115.200],
            'peguyangan' => ['Peguyangan', -8.620, 115.210],
            'peguyangankaja' => ['Peguyangan Kaja', -8.610, 115.205],
            'peguyangankangin' => ['Peguyangan Kangin', -8.615, 115.220],
            'pemecutankaja' => ['Pemecutan Kaja', -8.645, 115.200],
            'ubungkaja' => ['Ubung Kaja', -8.620, 115.195],
            'penatih' => ['Penatih', -8.625, 115.250],
            'penatihdanginpuri' => ['Penatih Dangin Puri', -8.635, 115.245],
            'kesimankertalangu' => ['Kesiman Kertalangu', -8.650, 115.255],
            // Gianyar villages
            'kemenuh' => ['Kemenuh', -8.565, 115.310],
            'batuan' => ['Batuan', -8.585, 115.275],
            'mas' => ['Mas', -8.535, 115.270],
            'batubulan' => ['Batubulan', -8.625, 115.265],
            'batubulankangin' => ['Batubulan Kangin', -8.615, 115.270],
            'singapadu' => ['Singapadu', -8.590, 115.260],
            'singapadukaler' => ['Singapadu Kaler', -8.575, 115.255],
            'ketewel' => ['Ketewel', -8.635, 115.285],
            'saba' => ['Saba', -8.605, 115.335],
            'pering' => ['Pering', -8.610, 115.310],
            'medahan' => ['Medahan', -8.600, 115.340],
            'blahbatuh' => ['Blahbatuh', -8.580, 115.320],
            'kedewatan' => ['Kedewatan', -8.495, 115.245],
            'sayan' => ['Sayan', -8.500, 115.240],
            'singakerta' => ['Singakerta', -8.530, 115.245],
            'lodtunduh' => ['Lodtunduh', -8.545, 115.255],
            'peliatan' => ['Peliatan', -8.520, 115.275],
            'petulu' => ['Petulu', -8.485, 115.270],
            'bedulu' => ['Bedulu', -8.525, 115.290],
            'pejengkaja' => ['Pejeng Kaja', -8.500, 115.295],
            'pejengkawan' => ['Pejeng Kawan', -8.510, 115.290],
            'kelusa' => ['Kelusa', -8.460, 115.245],
            'keliki' => ['Keliki', -8.460, 115.270],
            'kenderan' => ['Kenderan', -8.430, 115.280],
            'sebatu' => ['Sebatu', -8.390, 115.295],
            'manukaya' => ['Manukaya', -8.400, 115.310],
            'tampaksiring' => ['Tampaksiring', -8.420, 115.310],
            // Tabanan villages
            'dauhpeken' => ['Dauh Peken', -8.540, 115.125],
            'beraban' => ['Beraban', -8.620, 115.090],
            'pandakgede' => ['Pandak Gede', -8.625, 115.115],
            'abiantuwung' => ['Abian Tuwung', -8.580, 115.150],
            'kerambitan' => ['Kerambitan', -8.560, 115.075],
            'marga' => ['Marga', -8.480, 115.165],
            'penebel' => ['Penebel', -8.445, 115.130],
            'wongayagede' => ['Wongaya Gede', -8.380, 115.105],
            'candikuning' => ['Candikuning', -8.280, 115.165],
            'pupuan' => ['Pupuan', -8.350, 114.980],
            'selemadeg' => ['Selemadeg', -8.490, 115.000],
            'selemadegtimur' => ['Selemadeg Timur', -8.510, 115.020],
            'selemadegbarat' => ['Selemadeg Barat', -8.470, 114.950],
            // Bangli, Klungkung, Karangasem, Buleleng
            'centralbatur' => ['Batur Tengah', -8.265, 115.360],
            'baturtengah' => ['Batur Tengah', -8.265, 115.360],
            'southbatur' => ['Batur Selatan', -8.280, 115.350],
            'songana' => ['Songan A', -8.230, 115.405],
            'kintamani' => ['Kintamani', -8.245, 115.325],
            'kedisan' => ['Kedisan', -8.265, 115.380],
            'jungutbatu' => ['Jungutbatu', -8.675, 115.450],
            'ped' => ['Ped', -8.680, 115.505],
            'banjarangkan' => ['Banjarangkan', -8.545, 115.370],
            'kubu' => ['Kubu', -8.250, 115.570],
            'sukasada' => ['Sukasada', -8.150, 115.100],
            'seririt' => ['Seririt', -8.195, 114.930],
            'kaliasem' => ['Kaliasem', -8.160, 115.000],
        ],
    ];

    /** First 3 digits of a postcode => PLACES key, for Google rows that only have a postcode ("80221,Bali,Indonesia"). */
    private const POSTCODE_PREFIXES = [
        'bali' => [
            '801' => 'denpasar', '802' => 'denpasar', '803' => 'badung', '805' => 'gianyar', '806' => 'bangli',
            '807' => 'klungkung', '808' => 'karangasem', '811' => 'buleleng', '821' => 'tabanan', '822' => 'jembrana',
        ],
    ];

    private ?array $countries = null;

    /**
     * Lowercase letters only, without the administrative words the platforms add or drop
     * ("Badung Regency", "Special Region of Yogyakarta", "Bangka-Belitung Islands").
     */
    public static function normalize(?string $name): string
    {
        $name = strtolower((string) $name);
        $name = preg_replace('/\b(special capital region of|special region of|daerah istimewa|dki|province|provinsi|regency|kabupaten|kab|city|kota)\b/', '', $name);

        return preg_replace('/[^a-z]/', '', $name);
    }

    /**
     * Map point for one reported location, or null when it can't be placed (unknown country,
     * or a home-country row without a recognisable province).
     *
     * Abroad rows are grouped per country; at home we go down to province, or city in PLACES.
     *
     * @return array{key: string, label: string, region: ?string, country_code: string, scope: string, precision: string, lat: float, lng: float}|null
     */
    public function locate(?string $countryCode, ?string $region, ?string $city): ?array
    {
        $countryCode = $countryCode ? strtoupper($countryCode) : null;

        if (! $countryCode) {
            return null;
        }

        if ($countryCode !== self::HOME_COUNTRY) {
            $centroid = $this->countries()[$countryCode] ?? null;

            return $centroid ? [
                'key' => $countryCode,
                'label' => $countryCode, // the dashboard shows the localized country name
                'region' => null,
                'country_code' => $countryCode,
                'scope' => 'abroad',
                'precision' => 'country',
                'lat' => $centroid[0],
                'lng' => $centroid[1],
            ] : null;
        }

        $provinceKey = $this->provinceKey($region);
        if (! $provinceKey) {
            return null;
        }

        [$provinceName, $lat, $lng] = self::PROVINCES[$provinceKey];
        $placeKey = preg_match('/^\d{5}$/', (string) $city)
            ? (self::POSTCODE_PREFIXES[$provinceKey][substr($city, 0, 3)] ?? null)
            : self::normalize($city);
        $place = self::PLACES[$provinceKey][$placeKey] ?? null;

        if ($place) {
            return [
                'key' => "{$countryCode}|{$provinceKey}|" . self::normalize($place[0]),
                'label' => $place[0],
                'region' => $provinceName,
                'country_code' => $countryCode,
                'scope' => 'domestic',
                'precision' => 'city',
                'lat' => $place[1],
                'lng' => $place[2],
            ];
        }

        return [
            'key' => "{$countryCode}|{$provinceKey}",
            'label' => $provinceName,
            'region' => $provinceName,
            'country_code' => $countryCode,
            'scope' => 'domestic',
            'precision' => 'region',
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    private function provinceKey(?string $region): ?string
    {
        $key = self::normalize($region);
        $key = self::PROVINCE_ALIASES[$key] ?? $key;

        return isset(self::PROVINCES[$key]) ? $key : null;
    }

    /** @return array<string, array{0: float, 1: float}> ISO country code => [lat, lng] */
    private function countries(): array
    {
        return $this->countries ??= json_decode(file_get_contents(resource_path('data/country-centroids.json')), true);
    }
}
