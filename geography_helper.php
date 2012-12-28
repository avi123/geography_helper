<?php

function city_mapper() {
    return [
        "New York City" => "New York",
        "NY" => "New York",
        "NYC" => "New York"
    ];
}

function state_list() {
    return [
        'AL' => 'Alabama',
        'AK' => 'Alaska',
        'AZ' => 'Arizona',
        'AR' => 'Arkansas',
        'CA' => 'California',
        'CO' => 'Colorado',
        'CT' => 'Connecticut',
        'DE' => 'Delaware',
        'DC' => 'District Of Columbia',
        'FL' => 'Florida',
        'GA' => 'Georgia',
        'HI' => 'Hawaii',
        'ID' => 'Idaho',
        'IL' => 'Illinois',
        'IN' => 'Indiana',
        'IA' => 'Iowa',
        'KS' => 'Kansas',
        'KY' => 'Kentucky',
        'LA' => 'Louisiana',
        'ME' => 'Maine',
        'MD' => 'Maryland',
        'MA' => 'Massachusetts',
        'MI' => 'Michigan',
        'MN' => 'Minnesota',
        'MS' => 'Mississippi',
        'MO' => 'Missouri',
        'MT' => 'Montana',
        'NE' => 'Nebraska',
        'NV' => 'Nevada',
        'NH' => 'New Hampshire',
        'NJ' => 'New Jersey',
        'NM' => 'New Mexico',
        'NY' => 'New York',
        'NC' => 'North Carolina',
        'ND' => 'North Dakota',
        'OH' => 'Ohio',
        'OK' => 'Oklahoma',
        'OR' => 'Oregon',
        'PA' => 'Pennsylvania',
        'RI' => 'Rhode Island',
        'SC' => 'South Carolina',
        'SD' => 'South Dakota',
        'TN' => 'Tennessee',
        'TX' => 'Texas',
        'UT' => 'Utah',
        'VT' => 'Vermont',
        'VA' => 'Virginia',
        'WA' => 'Washington',
        'WV' => 'West Virginia',
        'WI' => 'Wisconsin',
        'WY' => 'Wyoming'
    ];
}

function get_state_abbreviation($state_name) {
    $state_list = array_flip(state_list());
    return isset($state_list[$state_name]) ? $state_list[$state_name] : false;
}

function geocode($address) {
    $address = urlencode($address);
    $retries = 0;
    $response = json_decode(
        file_get_contents(
            "http://maps.googleapis.com/maps/api/geocode/json?address=$address&sensor=false&region=us"
        ), true
    );
    while($response['status'] == 'OVER_QUERY_LIMIT' && $retries < 5) {
        sleep(1);
        $response = json_decode(
            file_get_contents(
                "http://maps.googleapis.com/maps/api/geocode/json?address=$address&sensor=false&region=us"
            ), true
        );
        $retries++;
    }
    return $response;
}

function get_city_state_from_zipcode($zipcode) {
    $CI = &get_instance();
    $CI->load->model('city_state_zipcode_cache_model');
    $addressarray = $CI->city_state_zipcode_cache_model->get_city_state_from_zipcode($zipcode);


    return (!empty($addressarray) ||
        ($addressarray = get_city_state_from_zipcode_geonames($zipcode,$CI->city_state_zipcode_cache_model))) ?
        array('city' => $addressarray['city'], 'state' => $addressarray['state']) :
        false;
}

function get_city_state_from_zipcode_geonames($zipcode, &$city_state_zipcode_cache_model) {
    $response = json_decode(
        file_get_contents(
            "http://api.geonames.org/postalCodeLookupJSON?postalcode=$zipcode&country=US&username=aviberkowitz"
        ), true
    );
    if(is_array($response['postalcodes'])) {
        $citymapper = city_mapper();
        $addressarray = $response['postalcodes'][0];
        $addressarray['city'] = $addressarray['placeName'];
        $addressarray['city'] = isset($citymapper[$addressarray['city']]) ?
            $citymapper[$addressarray['city']] :
            $addressarray['city'];
        $addressarray['state'] = $addressarray['adminCode1'];
        $city_state_zipcode_cache_model->cache_city_state_for_zipcode(
            $addressarray['city'],
            $addressarray['state'],
            $zipcode
        );

        return $addressarray;
    } else {
        print_r($response);
        return false;
    }
}

function get_formatted_address_from_address($address, &$model = null) {
    $formatted_address = isset($model) && ($formatted_address = $model->get_normalized_address_from_address($address))
        ? $formatted_address
        : call_user_func(function($address, $model) {
            $results = geocode($address)['results'];
            $formatted_address = isset($results[0]) ? $results[0]['formatted_address'] : false;
            $formatted_address && isset($model) ?
                $model->cache_normalized_address_for_address($address,$formatted_address) : null;
            return $formatted_address;
        }, $address, $model);
    return $formatted_address;
}

function get_formatted_address_from_zipcode($zipcode) {
    $response = geocode($zipcode);
    return $response['results'][0]['formatted_address'];
}

function get_latitude_longitude_from_address($address) {
    $response = geocode($address);

    return [
        'latitude' => $response['results'][0]['geometry']['location']['lat'],
        'longitude' => $response['results'][0]['geometry']['location']['lng'],
        'geocode_response' => json_encode($response)
    ];
}
/*
function reverse_geocode($latitude,$longitude) {
    $response = json_decode(file_get_contents("http://maps.googleapis.com/maps/api/geocode/json?latlng=$latitude,$longitude&sensor=false"),true);
}
*/
