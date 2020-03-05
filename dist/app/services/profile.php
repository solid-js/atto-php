<?php

// Load DB Connector
require_once( APP_ROOT.'utils/DBConnector.php' );

/**
 * Get radio code from an int.
 * Value is modulo based so > 26 will loop
 * @param $int
 * @return string
 */
function getRadioCode ( $int )
{
    $radioCodes = [
        'Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf',
        'Hotel', 'India', 'Juliett', 'Kilo', 'Lima', 'Mike', 'November',
        'Oscar', 'Papa', 'Quebec', 'Romeo', 'Sierra', 'Tango', 'Uniform',
        'Victor', 'Whiskey', 'X', 'Yankee', 'Zulu'
    ];
    return strtolower( $radioCodes[ $int % count($radioCodes)] );
}

/**
 * Get hexadecimal value from a string
 * @param $from
 * @param $start
 * @param $length
 * @return float|int
 */
function hexPart ( $from, $start, $length )
{
    return hexdec( substr($from, $start, $length) );
}

/**
 * Add zeros until 999
 * @param $num
 * @return string
 */
function zeroFill ( $num )
{
    $out = '';
    if ( $num < 10 )
        $out .= '0';
    if ( $num < 100 )
        $out .= '0';
    return $out.$num;
}

/**
 * Get current time in ms
 * @return int
 */
function getCurrentTimeInMS ()
{
    return round(microtime( true ) * 1000);
}

/**
 * Create unique code like 012-alpha-876-zulu-591
 * @return array
 */
function getRandomToken ()
{
    // Get current time in ms to have 1000 slots by seconds available
    $time = getCurrentTimeInMS();

    // Convert it to sha1 to avoid collisions and create very different codes
    $sha = sha1( $time );

    // Create code like : 012-alpha-876-zulu-591
    $parts = [
        zeroFill( hexPart($sha, 0, 3) % 1000 ),
        getRadioCode( hexPart($sha, 12, 2) ),
        zeroFill( hexPart($sha, 3, 3) % 1000 ),
        getRadioCode( hexPart($sha, 16, 2) ),
        zeroFill( hexPart($sha, 6, 3) % 1000 ),
    ];

    // Return time, sha and generated code for database and user
    return [
        'time'  => $time,
        'sha'   => $sha,
        'code'  => implode('-', $parts)
    ];
}

class ProfileService
{
    // ------------------------------------------------------------------------- CONFIG

    /**
     * Max time to keep profile code while creation.
     * TODO : Quit page if user stays on profile setup for more that 10 min
     * @var array
     */
    protected $_codeExpiration = 10 * 60 * 1000; // In ms - 10 min

    // ------------------------------------------------------------------------- INIT

    /**
     * @var DBConnector
     */
    protected $_connector;

    /**
     * If we are instantiated from another service
     * @var bool
     */
    protected $_fromService;

    /**
     * ProfileService constructor.
     */
    public function __construct ( $fromService = false )
    {
        // Try to connect and return error
        $this->_connector = DBConnector::instance();
        if ( !$this->_connector->connect() )
            _::json(['code' => 10, 'message' => 'Unable to connect.'], 1);

        // If we are instantiated from another service
        $this->_fromService = $fromService;
    }

    // ------------------------------------------------------------------------- REQUEST

    /**
     * Request a user code.
     * This will generate a random code based on a timestamp SHA1 token.
     */
    public function requestCode ()
    {
        $tries = 0;
        while ( true )
        {
            // Stop if we tried 10 codes
            if ( ++$tries > 10 ) return [
                'code' => 100,
                'message' => 'Too many tries.'
            ];

            // Get a new random token, based on current time in ms
            $token = getRandomToken();

            // Check if this code is already in DB, it may happens
            $tokenInDB = $this->_connector->fetch('codes', [
                'code' => $token['code']
            ]);

            // This code is not in DB, we can use it
            if ( $tokenInDB === false ) break;

            // If this token has a profile id associated,
            // do not use it and search for an other one
            if ( !is_null($tokenInDB['profile_id']) ) continue;

            // If this token has not expired, user may be still in profile setup
            // do not use it and search for an other one
            $expirationTime = $tokenInDB['time'] + $this->_codeExpiration;
            if ( getCurrentTimeInMS() < $expirationTime ) continue;

            // This token is used but has expired and have to associated profile
            // Delete this token and exit loop to allow this code to be used
            if ( $this->_connector->delete( 'codes', ['code' => $token['code']] ) )
                break;
        }

        // Insert this code
        $insertStatement = $this->_connector->insert('codes', [
            'time' => $token['time'],
            'code' => $token['code']
        ]);

        // Return error if we did not managed to get this inserted
        if ( $insertStatement === false ) return [
            'code' => 101,
            'message' => 'Unable to insert code.'
        ];

        // Return token
        return [
            'success' => true,
            'token' => $token['code'],
            'tries' => $tries
        ];
    }

    // ------------------------------------------------------------------------- SET PROFILE

    public function setProfile ()
    {
        // ---- VALIDATE PARAMETERS

        // Check parameters existence
        if ( !isset($_POST['code']) || !isset($_POST['profile']) ) return [
            'code' => 110,
            'message' => 'Missing parameters.'
        ];

        // Get parameters
        $codeParam = $_POST['code'];
        $profileParam = $_POST['profile'];

        // Check parameters validity
        json_decode( $profileParam, true );
        if ( empty($codeParam) || json_last_error() !== JSON_ERROR_NONE ) return [
            'code' => 111,
            'message' => 'Invalid parameters.'
        ];

        // ---- GET TOKEN FROM DB

        // Get token from DB
        $tokenInDB = $this->_connector->fetch(
            'codes', [ 'code' => $codeParam ]
        );

        // Token not found
        if ( !$tokenInDB ) return [
            'code' => 112,
            'message' => 'Token not found.'
        ];

        // ---- GET PROFILE FROM TOKEN

        // Get associated profile
        $profileInDB = $this->_connector->fetch('profiles', [
            'id' => $tokenInDB['profile_id']
        ]);

        // Profile does not exists, insert it
        if ( !$profileInDB )
        {
            // Insert new profile data and get inserted ID
            $profileID = $this->_connector->insert('profiles', [
                'profile_data' => $profileParam
            ]);

            // Error while insertion
            if ( $profileID === false ) return [
                'code' => 120,
                'message' => 'Unable to create profile.'
            ];

            // Update code to link it to profile
            $success = $this->_connector->update('codes', [
                'id' => $tokenInDB['id']
            ], [
                'profile_id' => $profileID,
            ]);
        }

        // Profile does exists, update it
        else
        {
            // Directly update profile data from profile id
            $success = $this->_connector->update('profiles', [
                'id' => $tokenInDB['profile_id']
            ], [
                'profile_data' => $profileParam
            ]);
        }

        // Error while update
        if ( !$success ) return [
            'code' => 1221,
            'message' => 'Unable to update profile.'
        ];

        // Data successfully saved
        return [ 'success' => true ];
    }

    // ------------------------------------------------------------------------- GET PROFILE

    public function getProfile ()
    {
        // ---- VALIDATE PARAMETERS

        // Check parameters existence
        if ( !isset($_POST['code']) ) return [
            'code' => 110,
            'message' => 'Missing parameters.'
        ];

        // Get parameters
        $codeParam = $_POST['code'];

        // Check parameters validity
        if ( empty($codeParam) ) return [
            'code' => 111,
            'message' => 'Invalid parameters.'
        ];

        // ---- GET TOKEN FROM DB

        // Get token from DB
        $tokenInDB = $this->_connector->fetch('codes', [
            'code' => $codeParam
        ]);

        // Token not found
        if ( !$tokenInDB ) return [
            'code' => 112,
            'message' => 'Token not found.'
        ];

        // ---- GET PROFILE FROM TOKEN

        // Get associated profile
        $profileInDB = $this->_connector->fetch('profiles', [
            'id' => $tokenInDB['profile_id']
        ]);

        // Profile not found
        if ( !$profileInDB ) return [
            'code' => 113,
            'message' => 'Profile not found.'
        ];

        // ---- RETURN PROFILE AND MISSION DATA

        // Return loaded profile
        $data = [
            'profile' => json_decode($profileInDB['profile_data'], true),
            'missions' => (
                ! is_null($profileInDB['missions_data'])
                ? json_decode($profileInDB['missions_data'], true)
                : []
            )
        ];

        // Add profile ID if we are instantiated from another service
        if ( $this->_fromService )
            $data['profile_id'] = $tokenInDB['profile_id'];

        return $data;
    }

    // ------------------------------------------------------------------------- TEST API

    public function index ()
    {
        ?><html><body>
        <h1>Profile API</h1><hr>

        <h2>Request code</h2>
        <form action="<?= _::href('api/profile/requestCode') ?>" method="post">
            <button type="submit">Submit</button>
        </form><hr>

        <h2>Get profile</h2>
        <form action="<?= _::href('api/profile/getProfile') ?>" method="post">
            <label>Code</label>
            <input type="text" name="code" />
            <br>
            <button type="submit">Submit</button>
        </form><hr>

        <h2>Set profile</h2>
        <form action="<?= _::href('api/profile/setProfile') ?>" method="post">
            <label>Code</label>
            <input type="text" name="code" />
            <br>
            <label>Profile</label>
            <input type="text" name="profile" />
            <br>
            <button type="submit">Submit</button>
        </form>

        </body></html><?php
    }
}
