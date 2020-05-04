<?php

namespace App\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class SyncCommand extends Command
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var \Google_Service_Reports
     */
    private $reportService;

    /**
     * @var \Google_Service_Drive
     */
    private $driveService;

    /**
     * @var \Google_Service_Sheets
     */
    private $spreadsheetService;

    /**
     * @var OutputInterface
     */
    private $output;

    private $spreadsheetRows;
    private $spreadsheetRowsToAdd;

    protected function configure()
    {
        $this->setName('sync');
        $this->setDescription('Extract log from google meet');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->client = $this->authorize();
        $this->fields = ['display_name', 'device_type', 'identifier', 'location_region', 'organizer_email', 'meeting_code', 'duration_seconds'];

        $this->reportService = new \Google_Service_Reports($this->client);
        $this->driveService = new \Google_Service_Drive($this->client);
        $this->spreadsheetService = new \Google_Service_Sheets($this->client);
        $this->getMeets();

    }


    private function authorize()
    {
        if ('cli' != php_sapi_name()) {
            throw new \Exception('This application must be run on the command line.');
        }

        if (file_exists(__DIR__ . '/../../meet.yaml')) {
            $this->config = Yaml::parse(file_get_contents(__DIR__ . '/../../meet.yaml'));
        } else {
            throw new \Exception('File ' . __DIR__ . '/../../meet.yaml not found.');
        }


        $client = new \Google_Client();
        $client->setApplicationName('Sync google meet logs');
        $client->setClientId($this->config['clientId']);
        $client->setClientSecret($this->config['clientSecret']);
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $client->setScopes(
            array(
                \Google_Service_Reports::ADMIN_REPORTS_AUDIT_READONLY,
                \Google_Service_Sheets::SPREADSHEETS,
                \Google_Service_Sheets::DRIVE_FILE,
            )
        );
        $client->setAccessType('offline');

        if ('' == $this->config['clientId'] || '' == $this->config['clientSecret']) {
            throw new \Exception('clientId or clientSecret empties.');
        }

        $tokenFilename = __DIR__ . '/../../googleAppsToken.json';
        if (file_exists($tokenFilename)) {
            $accessToken = json_decode(file_get_contents($tokenFilename), true);
        } else {
            $authUrl = $client->createAuthUrl();
            //Request authorization
            echo "\n* * * *  Prima autenticazione";
            echo "Inserisci nel browser:\n$authUrl\n\n";
            echo "Inserisci il codice di autenticazione:\n";
            $authCode = trim(fgets(STDIN));
            // Exchange authorization code for access token
            $accessToken = $client->authenticate($authCode);
            $client->setAccessToken($accessToken);
            file_put_contents($tokenFilename, json_encode($accessToken));
        }

        $client->setAccessToken($accessToken);
        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents($tokenFilename, json_encode($client->getAccessToken()));
        }

        return $client;
    }

    private function getMeets()
    {
        $userKey = 'all';
        $applicationName = 'meet';
        $optParams = array(
            'maxResults' => 400
        );
        $results = $this->reportService->activities->listActivities(
            $userKey, $applicationName, $optParams);

        if (count($results->getItems()) == 0) {
            print "No logins found.\n";
        } else {


            foreach ($results->getItems() as $activity) {
                $end = new \DateTime($activity->getId()->getTime());
                $end->setTimezone(new \DateTimeZone('Europe/Rome'));
                $data = $this->extractData($activity->getEvents()[0]->getParameters());

                $start = new \DateTime($activity->getId()->getTime() . ' - ' . $data['duration_seconds'] . ' seconds');
                $start->setTimezone(new \DateTimeZone('Europe/Rome'));

                $this->getDatasheet($start);
                $row = $this->buildRow($start, $end, $data);
                $this->addSpreadsheetRowIfNotExists($row);


            }
            $this->flushSpreadsheetRowsToAdd();
        }
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


    private function getDatasheet(\DateTime $start)
    {

        if (!isset($this->spreadsheetRows)) {
            $spreadsheetName = $start->format('Ymd');
            $googleSpreadsheetId = $this->checkSpreadsheet($spreadsheetName);
            $range = 'A2:I';
            $response = $this->spreadsheetService->spreadsheets_values->get($googleSpreadsheetId, $range);
            $this->spreadsheetRows = $response->getValues();
            $i = 2;
            if (is_array($this->spreadsheetRows)) {
                foreach ($this->spreadsheetRows as $k) {
                    $i++;
                }
            }
            $this->startingRow = $i;
            $this->googleSpreadsheetId = $googleSpreadsheetId;;
        }

    }


    private function addSpreadsheetRowIfNotExists($row)
    {


        if (!$this->rowExistsInSpreadsheet($row)) {
            $this->spreadsheetRows[] = $row;
            $this->spreadsheetRowsToAdd[] = $row;
        }
    }


    private function checkSpreadsheet($spreadsheetName)
    {
        $fileId = $this->spreadsheetExists($spreadsheetName, $this->config['folderId']);
        if (!$fileId) {
            $fileId = $this->createSpreadsheet($spreadsheetName, $this->config['folderId']);
        }

        return $fileId;
    }

    private function spreadsheetExists($fileName, $parentId = null)
    {
        $condition[] = "mimeType= 'application/vnd.google-apps.spreadsheet'";
        $condition[] = "name='$fileName'";
        $condition[] = 'trashed != true';

        if ($parentId) {
            $condition[] = "'$parentId' in parents";
        }

        $q = implode(' AND ', $condition);

        $pageToken = null;
        do {
            $response = $this->driveService->files->listFiles(array(
                'q' => $q,
                'spaces' => 'drive',
                'pageToken' => $pageToken,
                'fields' => 'nextPageToken, files(id, name)',
            ));
            foreach ($response->files as $file) {
                return $file->id;
            }

            $pageToken = $response->pageToken;
        } while (null != $pageToken);

        return false;
    }

    private function createSpreadsheet($filename, $parentId = null)
    {
        $condition['name'] = $filename;
        $condition['mimeType'] = 'application/vnd.google-apps.spreadsheet';

        if ($parentId) {
            $condition['parents'] = [$parentId];
        }
        $fileMetadata = new \Google_Service_Drive_DriveFile($condition);
        $this->driveService->files->create($fileMetadata, array(
            'fields' => 'id',));
        $googleSpreadsheetId = $this->spreadsheetExists($filename, $parentId);

        $this->setupSpreadsheetHeader($googleSpreadsheetId);

        return $googleSpreadsheetId;
    }

    private function setupSpreadsheetHeader($googleSpreadsheetId)
    {
        $values = [['Inizio', 'Fine', 'Codice meet', 'Email organizzatore', 'Nome partecipante', 'Identificativo partecipante', 'Zona di collegamento', 'Dispositivo', 'Durata connessione']];
        $body = new \Google_Service_Sheets_ValueRange([
            'values' => $values,
        ]);
        $params = [
            'valueInputOption' => 'RAW',
        ];


        $range = 'A1:I1';
        $result = $this->spreadsheetService->spreadsheets_values->update($googleSpreadsheetId, $range,
            $body, $params);


    }

    private function rowExistsInSpreadsheet($row)
    {
        if (is_array($this->spreadsheetRows)) {
            unset($row[0]);
            unset($row[1]);

            foreach ($this->spreadsheetRows as $spreadsheetRow) {
                unset($spreadsheetRow[0]);
                unset($spreadsheetRow[1]);
                if ((
                    is_array($row)
                    && is_array($spreadsheetRow)
                    && count($row) == count($spreadsheetRow)
                    && array_diff($row, $spreadsheetRow) === array_diff($spreadsheetRow, $row)
                )) {
                    return true;
                }
            }
        }
        return false;
    }

    private function flushSpreadsheetRowsToAdd()
    {
        if (is_array($this->spreadsheetRowsToAdd)) {
            $countRowsToAdd = count($this->spreadsheetRowsToAdd);
            if ($countRowsToAdd > 0) {
                $body = new \Google_Service_Sheets_ValueRange([
                    'values' => $this->spreadsheetRowsToAdd,
                ]);
                $params = [
                    'valueInputOption' => 'USER_ENTERED',
                ];


                $range = "A" . $this->startingRow . ":I" . ($this->startingRow + count($this->spreadsheetRowsToAdd));
                $result = $this->spreadsheetService->spreadsheets_values->update($this->googleSpreadsheetId, $range,
                    $body, $params);
            }
        }
    }

    private function buildRow(\DateTime $start, \DateTime $end, array $data)
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
            $data['duration_seconds'],
        ];
    }
}
