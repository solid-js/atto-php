<?php

// Load DB Connector
require_once( APP_ROOT.'utils/DBConnector.php' );


class SubscribeService
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

    // -------------------------------------------------------------------------

    public function subscribe ()
    {
        // Connect to profile
        $profileResponse = $this->_profileService->getProfile();
        $userMissions = $profileResponse['missions'];

        // Missions data from config
        $missionsData = $this->_config['missions'];

        // Check if user completed all missions
        $allMissionsComplete = true;
        foreach ( $missionsData as $key => $mission )
        {
            if ( !isset($userMissions[ $key ]) || !$userMissions[ $key ] )
                $allMissionsComplete = false;
        }

        // Not completed
        if ( !$allMissionsComplete ) return [
            'code' => 140,
            'message' => 'Missions not completed.'
        ];

        // Check user consent
        if ( !isset($_POST['consent']) || $_POST['consent'] != 'on' ) return [
            'code' => 111,
            'message' => 'User must consent to subscribe.'
        ];

        // Check parameters existence
        if (
               !isset( $_POST['firstname'] )
            || !isset( $_POST['lastname'] )
            || !isset( $_POST['email'] )
            || !isset( $_POST['address'] )
            || !isset( $_POST['zip'] )
            || !isset( $_POST['city'] )
            || !isset( $_POST['country'] )
        )
            return [
                'code' => 110,
                'message' => 'Missing parameters.'
            ];

        // Check parameters emptiness
        if (
               empty( $_POST['firstname'] )
            || empty( $_POST['lastname'] )
            || empty( $_POST['email'] )
            || empty( $_POST['address'] )
            //|| empty( $_POST['zip'] ) // Do not force zip code to be filled
            || empty( $_POST['city'] )
            || empty( $_POST['country'] )
        )
            return [
                'code' => 111,
                'message' => 'Invalid parameters.'
            ];

        // Check email
        $email = $_POST['email'];
        if ( stripos($email, '@') === -1 || stripos($email, '.') === -1 )
            return [
                'code' => 112,
                'message' => 'Invalid email.'
            ];

        // Save to db
        $statement = $this->_connector->insert('subscriptions', [
            'profile_id'    => $profileResponse['profile_id'],
            'created_at'    => time(),
            'firstname'     => $_POST['firstname'],
            'lastname'      => $_POST['lastname'],
            'email'         => $_POST['email'],
            'address'       => $_POST['address'],
            'zip'           => $_POST['zip'],
            'country'       => $_POST['country'],
            'consent'       => 1
        ]);

        //_::dump( $this->_connector->getConnection()->errorInfo() );

        // Error while saving
        if ( $statement === false ) return [
            'code' => 113,
            'message' => 'Unable to save subscription.'
        ];

        // All good !
        return ['success' => true];
    }

    // ------------------------------------------------------------------------- TEST API

    public function index ()
    {
        ?><html><body>
        <h1>Subscribe API</h1><hr>

        <h2>Subscribe</h2>
        <form action="<?= _::href('api/subscribe/subscribe') ?>" method="post">
            <label>Code</label>
            <input type="text" name="code" />
            <br>
            <label>First name</label>
            <input type="text" name="firstname" />
            <br>
            <label>Last name</label>
            <input type="text" name="lastname" />
            <br>
            <label>Email address</label>
            <input type="text" name="email" />
            <br>
            <label>Postal address</label>
            <input type="text" name="address" />
            <br>
            <label>Zip code</label>
            <input type="text" name="zip" />
            <br>
            <label>City</label>
            <input type="text" name="city" />
            <br>
            <label>Country</label>
            <input type="text" name="country" />
            <br>
            <label>Consent</label>
            <input type="checkbox" name="consent" />
            <br>
            <button type="submit">Submit</button>
        </form>

        </body></html><?php
    }
}
