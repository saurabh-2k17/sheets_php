<?php

require __DIR__ . '/vendor/autoload.php';

// Reading data from spreadsheet.
$client = new \Google_Client();
$client->setApplicationName('Google Sheets and PHP');
$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
$client->setAccessType('offline');
$client->setAuthConfig(__DIR__ . '/credentials.json');
$service = new Google_Service_Sheets($client);

// Step 1: Change the SpreadSheet ID.
// Drupal Practice Spreadsheet ID - 1A4RASO6eKfG-l2VF6zLnW8U5C1lRIRHWqQnir2n824k
// Monthly Spreadsheet ID - 1RbeKJys9L3p37tEg_-wQYZSdisAzPsXhZW5LwD5q8Wc

$spreadsheetId = "1A4RASO6eKfG-l2VF6zLnW8U5C1lRIRHWqQnir2n824k";

// User Input for reading columns. like A2:G10.
echo "Please mention the columns to be read \n";
$read_input = rtrim(fgets(STDIN));

// Step 2: Change the Sheet Name.
$get_range = "Core Issues!" . $read_input;

// Request to get data from spreadsheet.
$response = $service->spreadsheets_values->get($spreadsheetId, $get_range);
$values = $response->getValues();

if (empty($values)) {
  print "No data found.\n";
}
else {
  // API URL.
  $websiteUrl = 'https://www.drupal.org/api-d7/node.json?type=project_issue&nid=';

  // From which row would you like to start. Like 2
  echo "Please mention the starting index: \n";
  $update_start_index = rtrim(fgets(STDIN));

  // Till which row would you like to update. Like 10
  echo "Please mention the last index: \n";
    $update_stop_index = rtrim(fgets(STDIN));

  $failure_cases = $success_cases = 0;

  foreach ($values as $key => $value) {

    $issue_url = $value[2];

    if (isset($value[4])) {
      $author = $value[4];
    }
    else {
      $author = '';
    }

    if (isset($value[5])) {
      $author_comment = $value[5];
    }
    else {
      $author_comment = '';
    }

    if (isset($value[6])) {
      $issue_picked_date = $value[6];
    }
    else {
      $issue_picked_date = '';
    }

    $issue_obj = explode("/", $issue_url);

    if (!empty($issue_obj[4])) {
      $project_name = $issue_obj[4];
    }
    else {
      print_r("\033[01;31mProject name not found " . $update_start_index . "\033[0m\n");
    }

    if (!empty($issue_obj[6])) {
      $issue_id = $issue_obj[6];
    }
    else {
      print_r("\033[01;31mIssue ID not found " . $update_start_index . "\033[0m\n");
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $websiteUrl . $issue_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    $issue_arrayobj = json_decode($response);
    if (isset($issue_arrayobj->list[0]->title)) {
      $success_cases++;
      $issue_title = $issue_arrayobj->list[0]->title;
      $issue_status_id = $issue_arrayobj->list[0]->field_issue_status;
      $changed_date = $issue_arrayobj->list[0]->changed;

      // Check if issue is closed, then find the Credit date, patch commit date.
      if ($issue_status_id !== '1' && $issue_status_id !== '4' && $issue_status_id !== '8' && $issue_status_id !== '13' && $issue_status_id !== '14' && $issue_status_id !== '15' && $issue_status_id !== '16') {
        $closed_date = date('d/m/Y', $changed_date);

        $issue_comment_obj = $issue_arrayobj->list[0]->comments;
        $issue_comment_obj = array_reverse($issue_comment_obj);

        foreach ($issue_comment_obj as $key => $issue_comment) {

          $issue_comment_id = $issue_comment->id;
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, 'https://www.drupal.org/api-d7/comment.json?cid=' . $issue_comment_id);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
          $response = curl_exec($ch);
          $err = curl_error($ch);
          curl_close($ch);

          $issue_commentid_obj = json_decode($response);

          if (isset($issue_commentid_obj->list[0]->comment_body->value)) {
            $comment = $issue_commentid_obj->list[0]->comment_body->value;
          }
          else {
            $comment = '';
          }
          if (strpos($comment, 'commitlog') !== FALSE) {
            $credit_timestamp = $issue_commentid_obj->list[0]->created;
            $credit_date = date('d/m/Y', $credit_timestamp);
            echo "\033[00;32mSuccess = " . "Issue Credit Date Found Comment Number " . $key . " Bottom" . "\033[0m\n";
            break;
          }
          else {
            $credit_date = '';
            echo "\033[00;35mFailure = " . "Issue Credit Date Not Found For Comment Number " . $key . " Bottom" . "\033[0m\n";
          }
        }
      }
      else {
        $closed_date = '';
        $credit_date = '';
      }

    }
    else {
      $failure_cases++;
      print_r("\033[00;31mInvalid JSON Response failed for " . $update_start_index . "\033[0m\n");
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

    // Step 3: Change the Sheet Name, Column and values.

    // Request to update the spreadsheet, for Drupal Practice Spreadsheet.
    $update_range = "Core Issues!" . "A" . $update_start_index . ":" . "I" . $update_start_index;
    $values = [[$issue_title, $issue_status, $issue_url, $project_name, $author, $author_comment, $issue_picked_date, $credit_date, $closed_date]];

    // Request to update the spreadsheet, for Monthly Spreadsheet.
//    $update_range = "April-2020 [Internal]!" . "A" . $update_start_index . ":" . "I" . $update_start_index;
//    //$values = [[$issue_title, $issue_stat us, $issue_url]];
//    $values = [[$issue_title, $issue_status, $issue_url, $project_name, $author, $author_comment, $issue_picked_date, $credit_date, $closed_date]];

    if (isset($issue_arrayobj->list[0]->title)) {
      echo 'UPDATING ' . $update_range . PHP_EOL;
    }
    $body = new Google_Service_Sheets_ValueRange([
      'values' => $values,
    ]);
    $params = [
      'valueInputOption' => 'USER_ENTERED',
    ];

    $update_sheet = $service->spreadsheets_values->update($spreadsheetId, $update_range, $body, $params);

    if ($update_start_index <= $update_stop_index) {
      $update_start_index++;
    }
  }
  echo "\033[00;32mSuccess = " . $success_cases . "\033[0m\n";
  echo "\033[00;31mFailure = " . $failure_cases . "\033[0m\n";
  echo "\033[00;33mUpdate Completed \033[0m\n";
}
