<?php

require __DIR__ . '/vendor/autoload.php';

//Reading data from spreadsheet.
$client = new \Google_Client();
$client->setApplicationName('Google Sheets and PHP');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$client->setAuthConfig(__DIR__ . '/credentials.json');
$service = new Google_Service_Sheets($client);

$spreadsheetId = "1A4RASO6eKfG-l2VF6zLnW8U5C1lRIRHWqQnir2n824k";

//User Input for reading columns.
echo "Please mention the columns to be read \n";
$read_input = rtrim(fgets(STDIN));

$get_range = "Contrib issues!" . $read_input;

//Request to get data from spreadsheet.
$response = $service->spreadsheets_values->get($spreadsheetId, $get_range);
$values = $response->getValues();

if (empty($values)) {
  print "No data found.\n";
}
else {
  $websiteUrl = 'https://www.drupal.org/api-d7/node.json?type=project_issue&nid=';

  //User Input for updating columns.
  echo "Please mention the starting index: \n";
  $update_start_index = rtrim(fgets(STDIN));

  echo "Please mention the last index: \n";
  $update_stop_index = rtrim(fgets(STDIN));
  
  $failure_cases = 0;
  $success_cases = 0;
  
  foreach ($values as $key => $value) {

    $issue_url = $value[2];
    $issue_obj = (explode("/", $issue_url));
    if (isset($issue_obj[6])) {
      $issue_id = $issue_obj[6];
    }
    else {
      print_r("\033[01;31mIssue ID not found " . $update_start_index . "\033[0m\n");
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $websiteUrl . $issue_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    $issue_arrayobj = json_decode($response);
    if (isset($issue_arrayobj->list[0]->title)) {
      $success_cases ++;
      $issue_title = $issue_arrayobj->list[0]->title;
      $issue_status_id = $issue_arrayobj->list[0]->field_issue_status;
    } else {
        $failure_cases ++;
        print_r ("\033[01;31mInvalid JSON Response failed for " . $update_start_index  . "\033[0m\n");
        $issue_title = '';
        $issue_status_id = '';
    }
  
    switch ($issue_status_id) {
      case 1:
        $issue_status = 'Active';
        break;
      case 2:
        $issue_status = 'Fixed';
        break;
      case 3:
        $issue_status = 'Closed (duplicate)';
        break;
      case 4:
        $issue_status = 'Postponed';
        break;
      case 5:
        $issue_status = 'Closed (won\'t fix)';
        break;
      case 6:
        $issue_status = 'Closed (works as designed)';
        break;
      case 7:
        $issue_status = 'Closed (fixed)';
        break;
      case 8:
        $issue_status = 'Needs review';
        break;
      case 13:
        $issue_status = 'Needs work';
        break;
      case 14:
        $issue_status = 'Reviewed & tested by the community';
        break;
      case 15:
        $issue_status = 'Patch (to be ported)';
        break;
      case 16:
        $issue_status = 'Postponed (maintainer needs more info)';
        break;
      case 17:
        $issue_status = 'Closed (outdated)';
        break;
      case 18:
        $issue_status = 'Closed (cannot reproduce)';
        break;
    }

    //Request to update the spreadsheet.
    $update_range = "Contrib issues!" . "A" . $update_start_index . ":" . "B" . $update_start_index;

    $values = [[$issue_title, $issue_status]];
    
    if (isset($issue_arrayobj->list[0]->title)) {
      echo 'UPDATING ' . $update_range . PHP_EOL;
    }
    $body = new Google_Service_Sheets_ValueRange([
      'values' => $values
    ]);
    $params = [
      'valueInputOption' => 'RAW'
    ];

    $update_sheet = $service->spreadsheets_values->update($spreadsheetId, $update_range, $body, $params);
    
    if ($update_start_index <= $update_stop_index) {
      $update_start_index ++;
    }
  }
    echo "\033[01;32mSuccess = " . $success_cases . "\033[0m\n";
    echo "\033[01;31mFailure = " . $failure_cases . "\033[0m\n";
    echo "\033[01;33mUpdate Completed \033[0m\n";
}
