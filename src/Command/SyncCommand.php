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
     * @var OutputInterface
     */
    private $output;


    protected function configure()
    {
        $this->setName('sync');
        $this->setDescription('Extract log from google meet');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->client = $this->authorize();
        $this->fields = ['display_name', 'device_type', 'identifier', 'location_region','organizer_email', 'meeting_code'];

        $this->reportService = new \Google_Service_Reports($this->client);
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
            'maxResults' => 1000,
        );
        $results = $this->reportService->activities->listActivities(
            $userKey, $applicationName, $optParams);

        if (count($results->getItems()) == 0) {
            print "No logins found.\n";
        } else {
            print "Logins:\n";
            foreach ($results->getItems() as $activity) {
                echo "\r\n" . $activity->getId()->getTime() . ' ' . $activity->getEvents()[0]->getName();
                $data = $this->extractData($activity->getEvents()[0]->getParameters());
                foreach ($this->fields as $key){
                    $d=(isset($data[$key]))?$data[$key]:' ';
                    print "\t$d";
                }
            }
        }
    }


    private function extractData($items)
    {
        $data = [];
        foreach ($items as $item) {
            if (in_array($item->getName(), $this->fields)) {
                $data[$item->getName()] = trim($item->getValue());
            }
        }
        return $data;
    }
}
