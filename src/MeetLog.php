<?php

namespace App;

use DateTime;
use DateTimeZone;
use Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Reports;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use Symfony\Component\Yaml\Yaml;

class MeetLog
{
    private $KEY_FILE_LOCATION = __DIR__ . '/../googleAppsToken.json';

    /**
     * @var array
     */
    private $config;

    /**
     * @var Google_Service_Reports
     */
    private $reportService;

    /**
     * @var Google_Service_Drive
     */
    private $driveService;

    /**
     * @var Google_Service_Sheets
     */
    private $spreadsheetService;

    /**
     * @var string
     */
    private $spreadsheetId;

    /**
     * @var array
     */
    private $spreadsheetRows;

    /**
     * @var string[]
     */
    private $fields;

    /**
     * MeetLog constructor.
     */
    public function __construct()
    {
        $client = $this->authorize();
        $this->fields = ['display_name', 'device_type', 'identifier', 'location_region', 'organizer_email', 'meeting_code', 'duration_seconds'];

        $this->reportService = new Google_Service_Reports($client);
        $this->driveService = new Google_Service_Drive($client);
        $this->spreadsheetService = new Google_Service_Sheets($client);
    }

    private function authorize()
    {
        if ('cli' != php_sapi_name()) {
            throw new Exception('This application must be run on the command line.');
        }

        if (file_exists(__DIR__ . '/../meet.yaml')) {
            $this->config = Yaml::parse(file_get_contents(__DIR__ . '/../meet.yaml'));
        } else {
            throw new Exception('File ' . __DIR__ . '/../meet.yaml not found.');
        }

        $client = new Google_Client();
        $client->setApplicationName('Sync google meet logs');
        try {
            $client->setAuthConfig($this->KEY_FILE_LOCATION);
        } catch (Exception $e) {
            echo "Auth key not provided";
        }
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $client->setScopes(
            array(
                Google_Service_Reports::ADMIN_REPORTS_AUDIT_READONLY,
                Google_Service_Sheets::SPREADSHEETS,
                Google_Service_Drive::DRIVE,
            )
        );
        $client->setSubject($this->config['admin']);
        $client->setAccessType('offline');

        return $client;
    }

    public function getMeets($date)
    {
        $userKey = 'all';
        $applicationName = 'meet';
        if (!is_null($date)) {
            $startTime = (new DateTime($date . ' 7:00:00', new DateTimeZone('Europe/Rome')))->format(DateTime::RFC3339);
            $endTime = (new DateTime($date . ' 21:00:00', new DateTimeZone('Europe/Rome')))->format(DateTime::RFC3339);
        } else {
            $startTime = (new DateTime('yesterday 7:00:00', new DateTimeZone('Europe/Rome')))->format(DateTime::RFC3339);
            $endTime = (new DateTime('yesterday 21:00:00', new DateTimeZone('Europe/Rome')))->format(DateTime::RFC3339);
        }
        $optParams = array(
            'startTime' => $startTime,
            'endTime' => $endTime
        );

        $spreadsheetName = (new DateTime($date))->format('Ymd') . '-' . time();
        $this->spreadsheetId = $this->createSpreadsheet($spreadsheetName, $this->config['folderId']);

        $pageToken = null;

        do {
            if (null != $pageToken)
                $optParams['pageToken'] = $pageToken;

            $results = $this->reportService->activities->listActivities($userKey, $applicationName, $optParams);

            if (count($results->getItems()) == 0) {
                print "No logins found.\n";
            } else {
                foreach ($results->getItems() as $activity) {
                    $end = new DateTime($activity->getId()->getTime());
                    $end->setTimezone(new DateTimeZone('Europe/Rome'));
                    $data = $this->extractData($activity->getEvents()[0]->getParameters());

                    if (!isset($data['duration_seconds']) || $data['duration_seconds'] == 0)
                        $start = new DateTime($activity->getId()->getTime());
                    else
                        $start = new DateTime($activity->getId()->getTime() . ' - ' . $data['duration_seconds'] . ' seconds');

                    $start->setTimezone(new DateTimeZone('Europe/Rome'));
                    $row = $this->buildRow($start, $end, $data);
                    $this->spreadsheetRows[] = $row;
                }
                $this->flushSpreadsheetRows();
            }
            $pageToken = $results->nextPageToken;
        } while (null != $pageToken);
    }

    private function extractData($items)
    {
        $data = [];
        foreach ($items as $item) {
            if (in_array($item->getName(), $this->fields)) {
                $v = (!is_null($item->getIntValue())) ? $item->getIntValue() : $item->getValue();
                $data[$item->getName()] = trim($v);
            }
        }
        return $data;
    }

    private function createSpreadsheet($filename, $parentId = null)
    {
        $fileMetadata = new Google_Service_Drive_DriveFile();
        $fileMetadata->setName($filename);
        $fileMetadata->setMimeType('application/vnd.google-apps.spreadsheet');

        if ($parentId) {
            $fileMetadata->setParents([$parentId]);
        }

        $file = $this->driveService->files->create($fileMetadata, array('fields' => 'id',));
        $spreadsheetId = $file->getId();

        $this->setupSpreadsheetHeader($spreadsheetId);

        return $spreadsheetId;
    }

    private function setupSpreadsheetHeader($spreadsheetId)
    {
        $values = [['Inizio', 'Fine', 'Codice meet', 'Email organizzatore', 'Nome partecipante', 'Identificativo partecipante', 'Zona di collegamento', 'Dispositivo', 'Durata connessione']];
        $body = new Google_Service_Sheets_ValueRange();
        $body->setValues($values);
        $params = [
            'valueInputOption' => 'RAW',
        ];
        $range = 'A1:I1';
        $this->spreadsheetService->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
    }

    private function flushSpreadsheetRows()
    {
        if (is_array($this->spreadsheetRows)) {
            $countRowsToAdd = count($this->spreadsheetRows);
            if ($countRowsToAdd > 0) {
                $body = new Google_Service_Sheets_ValueRange();
                $body->setValues($this->spreadsheetRows);
                $params = [
                    'valueInputOption' => 'USER_ENTERED',
                ];

                echo "\r\n Righe da aggiungere: " . count($this->spreadsheetRows);
                $range = "A2";
                $this->spreadsheetService->spreadsheets_values->append($this->spreadsheetId, $range, $body, $params);
                $this->spreadsheetRows = [];
            }
        }
    }

    private function buildRow(DateTime $start, DateTime $end, array $data)
    {
        return [
            $start->format("d/m/Y H.i.s"),
            $end->format("d/m/Y H.i.s"),
            $data['meeting_code'] ?? '',
            $data['organizer_email'] ?? '',
            $data['display_name'] ?? '',
            $data['identifier'] ?? '',
            $data['location_region'] ?? '',
            $data['device_type'] ?? '',
            $data['duration_seconds'] ?? '',
        ];
    }
}