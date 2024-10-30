<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function contact_config()
{
    return [
        'name' => 'Domain Registrant Contact',
        'description' => 'Provides a contact form to reach the registrant of a specified domain.',
        'version' => '1.0',
        'author' => 'Namingo',
        'fields' => [
            'whmcsApiKey' => [
                'FriendlyName' => 'WHMCS API Key',
                'Type' => 'text',
                'Size' => '50',
                'Description' => 'Enter the WHMCS API key for sending emails.',
            ],
        ],
    ];
}

function contact_clientarea($vars)
{
    $modulelink = $vars['modulelink'];
    $systemUrl = $vars['systemurl'];
    $apiKey = $vars['whmcsApiKey'];
    $domain = $_GET['domain'] ?? null;

    $success = null;
    $error = null;

    // Check if the domain is provided and exists in WHMCS
    if ($domain) {
        $domainExists = Capsule::table('tbldomains')->where('domain', $domain)->first();
        
        if (!$domainExists) {
            $error = "Error: The specified domain does not exist.";
        }
    } else {
        $error = "Error: You must specify a domain.";
    }

    // Handle form submission if no error, and method is POST
    if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $submissionResult = contact_handleSubmission($domain, $apiKey);
        if ($submissionResult === true) {
            $success = "Your message has been sent successfully!";
        } else {
            $error = $submissionResult;
        }
    }

    return [
        'pagetitle' => 'Domain Registrant Contact',
        'breadcrumb' => ['index.php?m=contact' => 'Domain Registrant Contact'],
        'templatefile' => 'clientarea',
        'requirelogin' => false,
        'vars' => [
            'modulelink' => $modulelink,
            'systemurl' => $systemUrl,
            'domain' => $domain,
            'success' => $success,
            'error' => $error,
        ],
    ];
}

function contact_handleSubmission($domain, $apiKey)
{
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    // Check if the domain exists in WHMCS
    $domainExists = Capsule::table('tbldomains')->where('domain', $domain)->first();

    if (!$domainExists) {
        return "Error: The specified domain does not exist.";
    }

    // Send email via WHMCS API
    $userId = $domainExists->userid;
    $systemUrl = \WHMCS\Config\Setting::getValue('SystemURL');
    $apiUrl = $systemUrl . '/includes/api.php';

    $postfields = [
        'action' => 'SendEmail',
        'apikey' => $apiKey,
        'messagename' => 'Registrant Contact Message',
        'id' => $userId,
        'customsubject' => 'Contact Form Submission for Domain: ' . $domain,
        'custommessage' => "Name: $name\nEmail: $email\nMessage:\n$message",
    ];

    $queryString = http_build_query($postfields);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $queryString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // Check the response from WHMCS API and return a success or error message
    if ($response) {
        $decodedResponse = json_decode($response, true);
        if (isset($decodedResponse['result']) && $decodedResponse['result'] === 'success') {
            return true;
        }
        return $decodedResponse['message'] ?? 'Error: Unable to send the message.';
    }

    return 'Error: Unable to connect to WHMCS API.';
}