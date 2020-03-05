<?php

// Load DB Connector
require_once( APP_ROOT.'utils/DBConnector.php' );


class DashboardService
{
    // ------------------------------------------------------------------------- INIT

    /**
     * @var DBConnector
     */
    protected $_connector;

    protected $_profileService;

    protected $_config;

    /**
     * ProfileService constructor.
     */
    public function __construct ()
    {
        // Try to connect and return error
        $this->_connector = DBConnector::instance();
        if ( !$this->_connector->connect() )
            _::json(['code' => 10, 'message' => 'Unable to connect.'], 1);

        // Connect to profile service
        require_once( APP_ROOT.'services/profile.php' );
        $this->_profileService = new ProfileService( true );

        // Load config
        $this->_config = App::instance()->loadJSON('dashboard');
    }

    // ------------------------------------------------------------------------- GET MISSIONS

    public function getMissions ()
    {
        // Connect to profile
        $profileResponse = $this->_profileService->getProfile();
        $userMissions = $profileResponse['missions'];

        // Error while connecting to profile
        if ( isset($profileResponse['code']) ) return $profileResponse;

        // Browse all missions from config
        $missionsData = $this->_config['missions'];
        $returnedMissions = [];
        foreach ( $missionsData as $key => $mission )
        {
            // This is mission has been unlocked by user
            if ( isset($userMissions[$key]) && $userMissions[$key] )
                $returnedMissions[$key] = $mission['number'];

            // This missions is not opened yet
            else if ( $mission['timestamp'] > time() )
                $returnedMissions[$key] = 'locked';

            // This mission is opened and not unlocked by user
            else
                $returnedMissions[$key] = 'clue';
        }
        return $returnedMissions;
    }

    // ------------------------------------------------------------------------- TRY CODE

    public function unlock ()
    {
        // Connect to profile
        $profileResponse = $this->_profileService->getProfile();
        $userMissions = $profileResponse['missions'];

        // Missions data from config
        $missionsData = $this->_config['missions'];

        // Error while connecting to profile
        if ( isset($profileResponse['code']) ) return $profileResponse;

        // ---- VALIDATE PARAMETERS

        // Check parameters existence
        if ( !isset($_POST['mission']) || !isset($_POST['clue']) ) return [
            'code' => 110,
            'message' => 'Missing parameters.'
        ];

        // Get parameters
        $missionIndex   = intval($_POST['mission'], 10);
        $clueParam      = $_POST['clue'];

        // Check parameters validity
        if ( !is_numeric($_POST['mission']) || !isset( $missionsData[$missionIndex] ) )
            return [
                'code' => 111,
                'message' => 'Invalid parameters.'
            ];

        // Target current mission from index
        $currentMission = $missionsData[ $missionIndex ];

        // Mission already unlocked
        if ( isset( $userMissions[ $missionIndex ] ) && $userMissions[ $missionIndex ] )
            return [
                'code' => 130,
                'message' => 'Already unlocked.'
            ];

        // Check if this mission is timestamp enabled
        if ( time() < $currentMission['timestamp'] )
            return [
                'code' => 131,
                'message' => 'Mission closed.'
            ];

        // Check if this clue is the good one
        if ( strtolower( $clueParam ) != $currentMission['clue'] )
            return [
                'code' => 132,
                'message' => 'Invalid clue.'
            ];

        // Set mission as completed in user missions JSON
        $userMissions[ $missionIndex ] = 1;

        // Update in DB
        $updateStatement = $this->_connector->update('profiles', [
            'id' => $profileResponse['profile_id']
        ], [
            'missions_data' => json_encode( $userMissions, JSON_NUMERIC_CHECK )
        ]);

        // Update failed
        if ( !$updateStatement ) return [
            'code' => 133,
            'message' => 'Unable to save unlock code.'
        ];

        // Unlocked success !
        return [ 'success' => true ];
    }

    // ------------------------------------------------------------------------- TEST API

    public function index ()
    {
        ?><html><body>
        <h1>Dashboard API</h1><hr>

        <h2>Get missions</h2>
        <form action="<?= _::href('api/dashboard/getMissions') ?>" method="post">
            <label>Code</label>
            <input type="text" name="code" />
            <br>
            <button type="submit">Submit</button>
        </form><hr>

        <h2>Unlock</h2>
        <form action="<?= _::href('api/dashboard/unlock') ?>" method="post">
            <label>Code</label>
            <input type="text" name="code" />
            <br>
            <label>Mission Index</label>
            <input type="text" name="mission" />
            <br>
            <label>Clue</label>
            <input type="text" name="clue" />
            <br>
            <button type="submit">Submit</button>
        </form><hr>

        </body></html><?php
    }
}
