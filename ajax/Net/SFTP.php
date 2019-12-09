<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Pure-PHP implementation of SFTP.
 *
 * PHP versions 4 and 5
 *
 * Currently only supports SFTPv3, which, according to wikipedia.org, "is the most widely used version,
 * implemented by the popular OpenSSH SFTP server".  If you want SFTPv4/5/6 support, provide me with access
 * to an SFTPv4/5/6 server.
 *
 * The API for this library is modeled after the API from PHP's {@link http://php.net/book.ftp FTP extension}.
 *
 * Here's a short example of how to use this library:
 * <code>
 * <?php
 *    include('Net/SFTP.php');
 *
 *    $sftp = new Net_SFTP('www.domain.tld');
 *    if (!$sftp->login('username', 'password')) {
 *        exit('Login Failed');
 *    }
 *
 *    echo $sftp->pwd() . "\r\n";
 *    $sftp->put('filename.ext', 'hello, world!');
 *    print_r($sftp->nlist());
 * ?>
 * </code>
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category   Net
 * @package    Net_SFTP
 * @author     Jim Wigginton <terrafrost@php.net>
 * @copyright  MMIX Jim Wigginton
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link       http://phpseclib.sourceforge.net
 */

/**
 * Include Net_SSH2
 */
if (!class_exists('Net_SSH2')) {
    require_once('Net/SSH2.php');
}

/**#@+
 * @access public
 * @see Net_SFTP::getLog()
 */
/**
 * Returns the message numbers
 */
define('NET_SFTP_LOG_SIMPLE',  NET_SSH2_LOG_SIMPLE);
/**
 * Returns the message content
 */
define('NET_SFTP_LOG_COMPLEX', NET_SSH2_LOG_COMPLEX);
/**
 * Outputs the message content in real-time.
 */
define('NET_SFTP_LOG_REALTIME', 3);
/**#@-*/

/**
 * SFTP channel constant
 *
 * Net_SSH2::exec() uses 0 and Net_SSH2::read() / Net_SSH2::write() use 1.
 *
 * @see Net_SSH2::_send_channel_packet()
 * @see Net_SSH2::_get_channel_packet()
 * @access private
 */
define('NET_SFTP_CHANNEL', 2);

/**#@+
 * @access public
 * @see Net_SFTP::put()
 */
/**
 * Reads data from a local file.
 */
define('NET_SFTP_LOCAL_FILE', 1);
/**
 * Reads data from a string.
 */
// this value isn't really used anymore but i'm keeping it reserved for historical reasons
define('NET_SFTP_STRING',  2);
/**
 * Resumes an upload
 */
define('NET_SFTP_RESUME',  4);
/**#@-*/

/**
 * Pure-PHP implementations of SFTP.
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 * @version 0.1.0
 * @access  public
 * @package Net_SFTP
 */
class Net_SFTP extends Net_SSH2 {
    /**
     * Packet Types
     *
     * @see Net_SFTP::Net_SFTP()
     * @var Array
     * @access private
     */
    var $packet_types = array();

    /**
     * Status Codes
     *
     * @see Net_SFTP::Net_SFTP()
     * @var Array
     * @access private
     */
    var $status_codes = array();

    /**
     * The Request ID
     *
     * The request ID exists in the off chance that a packet is sent out-of-order.  Of course, this library doesn't support
     * concurrent actions, so it's somewhat academic, here.
     *
     * @var Integer
     * @see Net_SFTP::_send_sftp_packet()
     * @access private
     */
    var $request_id = false;

    /**
     * The Packet Type
     *
     * The request ID exists in the off chance that a packet is sent out-of-order.  Of course, this library doesn't support
     * concurrent actions, so it's somewhat academic, here.
     *
     * @var Integer
     * @see Net_SFTP::_get_sftp_packet()
     * @access private
     */
    var $packet_type = -1;

    /**
     * Packet Buffer
     *
     * @var String
     * @see Net_SFTP::_get_sftp_packet()
     * @access private
     */
    var $packet_buffer = '';

    /**
     * Extensions supported by the server
     *
     * @var Array
     * @see Net_SFTP::_initChannel()
     * @access private
     */
    var $extensions = array();

    /**
     * Server SFTP version
     *
     * @var Integer
     * @see Net_SFTP::_initChannel()
     * @access private
     */
    var $version;

    /**
     * Current working directory
     *
     * @var String
     * @see Net_SFTP::_realpath()
     * @see Net_SFTP::chdir()
     * @access private
     */
    var $pwd = false;

    /**
     * Packet Type Log
     *
     * @see Net_SFTP::getLog()
     * @var Array
     * @access private
     */
    var $packet_type_log = array();

    /**
     * Packet Log
     *
     * @see Net_SFTP::getLog()
     * @var Array
     * @access private
     */
    var $packet_log = array();

    /**
     * Error information
     *
     * @see Net_SFTP::getSFTPErrors()
     * @see Net_SFTP::getLastSFTPError()
     * @var String
     * @access private
     */
    var $sftp_errors = array();

    /**
     * File Type
     *
     * @see Net_SFTP::_parseLongname()
     * @var Integer
     * @access private
     */
    var $fileType = 0;

    /**
     * Directory Cache
     *
     * Rather than always having to open a directory and close it immediately there after to see if a file is a directory or
     * rather than always 
     *
     * @see Net_SFTP::_save_dir()
     * @see Net_SFTP::_remove_dir()
     * @see Net_SFTP::_is_dir()
     * @var Array
     * @access private
     */
    var $dirs = array();

    /**
     * Default Constructor.
     *
     * Connects to an SFTP server
     *
     * @param String $host
     * @param optional Integer $port
     * @param optional Integer $timeout
     * @return Net_SFTP
     * @access public
     */
    function Net_SFTP($host, $port = 22, $timeout = 10)
    {
        parent::Net_SSH2($host, $port, $timeout);
        $this->packet_types = array(
            1  => 'NET_SFTP_INIT',
            2  => 'NET_SFTP_VERSION',
            /* the format of SSH_FXP_OPEN changed between SFTPv4 and SFTPv5+:
                   SFTPv5+: http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.1.1
               pre-SFTPv5 : http://tools.ietf.org/html/draft-ietf-secsh-filexfer-04#section-6.3 */
            3  => 'NET_SFTP_OPEN',
            4  => 'NET_SFTP_CLOSE',
            5  => 'NET_SFTP_READ',
            6  => 'NET_SFTP_WRITE',
            7  => 'NET_SFTP_LSTAT',
            9  => 'NET_SFTP_SETSTAT',
            11 => 'NET_SFTP_OPENDIR',
            12 => 'NET_SFTP_READDIR',
            13 => 'NET_SFTP_REMOVE',
            14 => 'NET_SFTP_MKDIR',
            15 => 'NET_SFTP_RMDIR',
            16 => 'NET_SFTP_REALPATH',
            17 => 'NET_SFTP_STAT',
            /* the format of SSH_FXP_RENAME changed between SFTPv4 and SFTPv5+:
                   SFTPv5+: http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.3
               pre-SFTPv5 : http://tools.ietf.org/html/draft-ietf-secsh-filexfer-04#section-6.5 */
            18 => 'NET_SFTP_RENAME',

            101=> 'NET_SFTP_STATUS',
            102=> 'NET_SFTP_HANDLE',
            /* the format of SSH_FXP_NAME changed between SFTPv3 and SFTPv4+:
                   SFTPv4+: http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-9.4
               pre-SFTPv4 : http://tools.ietf.org/html/draft-ietf-secsh-filexfer-02#section-7 */
            103=> 'NET_SFTP_DATA',
            104=> 'NET_SFTP_NAME',
            105=> 'NET_SFTP_ATTRS',

            200=> 'NET_SFTP_EXTENDED'
        );
        $this->status_codes = array(
            0 => 'NET_SFTP_STATUS_OK',
            1 => 'NET_SFTP_STATUS_EOF',
            2 => 'NET_SFTP_STATUS_NO_SUCH_FILE',
            3 => 'NET_SFTP_STATUS_PERMISSION_DENIED',
            4 => 'NET_SFTP_STATUS_FAILURE',
            5 => 'NET_SFTP_STATUS_BAD_MESSAGE',
            6 => 'NET_SFTP_STATUS_NO_CONNECTION',
            7 => 'NET_SFTP_STATUS_CONNECTION_LOST',
            8 => 'NET_SFTP_STATUS_OP_UNSUPPORTED'
        );
        // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-7.1
        // the order, in this case, matters quite a lot - see Net_SFTP::_parseAttributes() to understand why
        $this->attributes = array(
            0x00000001 => 'NET_SFTP_ATTR_SIZE',
            0x00000002 => 'NET_SFTP_ATTR_UIDGID', // defined in SFTPv3, removed in SFTPv4+
            0x00000004 => 'NET_SFTP_ATTR_PERMISSIONS',
            0x00000008 => 'NET_SFTP_ATTR_ACCESSTIME',
            // 0x80000000 will yield a floating point on 32-bit systems and converting floating points to integers
            // yields inconsistent behavior depending on how php is compiled.  so we left shift -1 (which, in 
            // two's compliment, consists of all 1 bits) by 31.  on 64-bit systems this'll yield 0xFFFFFFFF80000000.
            // that's not a problem, however, and 'anded' and a 32-bit number, as all the leading 1 bits are ignored.
              -1 << 31 => 'NET_SFTP_ATTR_EXTENDED'
        );
        // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-04#section-6.3
        // the flag definitions change somewhat in SFTPv5+.  if SFTPv5+ support is added to this library, maybe name
        // the array for that $this->open5_flags and similarily alter the constant names.
        $this->open_flags = array(
            0x00000001 => 'NET_SFTP_OPEN_READ',
            0x00000002 => 'NET_SFTP_OPEN_WRITE',
            0x00000004 => 'NET_SFTP_OPEN_APPEND',
            0x00000008 => 'NET_SFTP_OPEN_CREATE',
            0x00000010 => 'NET_SFTP_OPEN_TRUNCATE'
        );
        // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-04#section-5.2
        // see Net_SFTP::_parseLongname() for an explanation
        $this->file_types = array(
            1 => 'NET_SFTP_TYPE_REGULAR',
            2 => 'NET_SFTP_TYPE_DIRECTORY',
            3 => 'NET_SFTP_TYPE_SYMLINK',
            4 => 'NET_SFTP_TYPE_SPECIAL'
        );
        $this->_define_array(
            $this->packet_types,
            $this->status_codes,
            $this->attributes,
            $this->open_flags,
            $this->file_types
        );
    }

    /**
     * Login
     *
     * @param String $username
     * @param optional String $password
     * @return Boolean
     * @access public
     */
    function login($username, $password = '')
    {
        if (!parent::login($username, $password)) {
            return false;
        }

        $this->window_size_client_to_server[NET_SFTP_CHANNEL] = $this->window_size;

        $packet = pack('CNa*N3',
            NET_SSH2_MSG_CHANNEL_OPEN, strlen('session'), 'session', NET_SFTP_CHANNEL, $this->window_size, 0x4000);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SFTP_CHANNEL] = NET_SSH2_MSG_CHANNEL_OPEN;

        $response = $this->_get_channel_packet(NET_SFTP_CHANNEL);
        if ($response === false) {
            return false;
        }

        $packet = pack('CNNa*CNa*',
            NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SFTP_CHANNEL], strlen('subsystem'), 'subsystem', 1, strlen('sftp'), 'sftp');
        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SFTP_CHANNEL] = NET_SSH2_MSG_CHANNEL_REQUEST;

        $response = $this->_get_channel_packet(NET_SFTP_CHANNEL);
        if ($response === false) {
            return false;
        }

        $this->channel_status[NET_SFTP_CHANNEL] = NET_SSH2_MSG_CHANNEL_DATA;

        if (!$this->_send_sftp_packet(NET_SFTP_INIT, "\0\0\0\3")) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_VERSION) {
            user_error('Expected SSH_FXP_VERSION', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Nversion', $this->_string_shift($response, 4)));
        $this->version = $version;
        while (!empty($response)) {
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $key = $this->_string_shift($response, $length);
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $value = $this->_string_shift($response, $length);
            $this->extensions[$key] = $value;
        }

        /*
         SFTPv4+ defines a 'newline' extension.  SFTPv3 seems to have unofficial support for it via 'newline@vandyke.com',
         however, I'm not sure what 'newline@vandyke.com' is supposed to do (the fact that it's unofficial means that it's
         not in the official SFTPv3 specs) and 'newline@vandyke.com' / 'newline' are likely not drop-in substitutes for
         one another due to the fact that 'newline' comes with a SSH_FXF_TEXT bitmask whereas it seems unlikely that
         'newline@vandyke.com' would.
        */
        /*
        if (isset($this->extensions['newline@vandyke.com'])) {
            $this->extensions['newline'] = $this->extensions['newline@vandyke.com'];
            unset($this->extensions['newline@vandyke.com']);
        }
        */

        $this->request_id = 1;

        /*
         A Note on SFTPv4/5/6 support:
         <http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-5.1> states the following:

         "If the client wishes to interoperate with servers that support noncontiguous version
          numbers it SHOULD send '3'"

         Given that the server only sends its version number after the client has already done so, the above
         seems to be suggesting that v3 should be the default version.  This makes sense given that v3 is the
         most popular.

         <http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-5.5> states the following;

         "If the server did not send the "versions" extension, or the version-from-list was not included, the
          server MAY send a status response describing the failure, but MUST then close the channel without
          processing any further requests."

         So what do you do if you have a client whose initial SSH_FXP_INIT packet says it implements v3 and
         a server whose initial SSH_FXP_VERSION reply says it implements v4 and only v4?  If it only implements
         v4, the "versions" extension is likely not going to have been sent so version re-negotiation as discussed
         in draft-ietf-secsh-filexfer-13 would be quite impossible.  As such, what Net_SFTP would do is close the
         channel and reopen it with a new and updated SSH_FXP_INIT packet.
        */
        if ($this->version != 3) {
            return false;
        }

        $this->pwd = $this->_realpath('.', false);

        $this->_save_dir($this->pwd);

        return true;
    }

    /**
     * Returns the current directory name
     *
     * @return Mixed
     * @access public
     */
    function pwd()
    {
        return $this->pwd;
    }

    /**
     * Canonicalize the Server-Side Path Name
     *
     * SFTP doesn't provide a mechanism by which the current working directory can be changed, so we'll emulate it.  Returns
     * the absolute (canonicalized) path.  If $mode is set to NET_SFTP_CONFIRM_DIR (as opposed to NET_SFTP_CONFIRM_NONE,
     * which is what it is set to by default), false is returned if $dir is not a valid directory.
     *
     * @see Net_SFTP::chdir()
     * @param String $dir
     * @param optional Integer $mode
     * @return Mixed
     * @access private
     */
    function _realpath($dir, $check_dir = true)
    {
        if ($check_dir && $this->_is_dir($dir)) {
            return true;
        }

        /*
        "This protocol represents file names as strings.  File names are
         assumed to use the slash ('/') character as a directory separator.

         File names starting with a slash are "absolute", and are relative to
         the root of the file system.  Names starting with any other character
         are relative to the user's default directory (home directory).  Note
         that identifying the user is assumed to take place outside of this
         protocol."

         -- http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-6
        */
        $file = '';
        if ($this->pwd !== false) {
            // if the SFTP server returned the canonicalized path even for non-existant files this wouldn't be necessary
            // on OpenSSH it isn't necessary but on other SFTP servers it is.  that and since the specs say nothing on
            // the subject, we'll go ahead and work around it with the following.
            if (empty($dir) || $dir[strlen($dir) - 1] != '/') {
                $file = basename($dir);
                $dir = dirname($dir);
            }

            $dir = $dir[0] == '/' ? '/' . rtrim(substr($dir, 1), '/') : rtrim($dir, '/');

            if ($dir == '.' || $dir == $this->pwd) {
                return $this->pwd . $file;
            }

            if ($dir[0] != '/') {
                $dir = $this->pwd . '/' . $dir;
            }
            // on the surface it seems like maybe resolving a path beginning with / is unnecessary, but such paths
            // can contain .'s and ..'s just like any other.  we could parse those out as appropriate or we can let
            // the server do it.  we'll do the latter.
        }

        /*
         that SSH_FXP_REALPATH returns SSH_FXP_NAME does not necessarily mean that anything actually exists at the
         specified path.  generally speaking, no attributes are returned with this particular SSH_FXP_NAME packet
         regardless of whether or not a file actually exists.  and in SFTPv3, the longname field and the filename
         field match for this particular SSH_FXP_NAME packet.  for other SSH_FXP_NAME packets, this will likely
         not be the case, but for this one, it is.
        */
        // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.9
        if (!$this->_send_sftp_packet(NET_SFTP_REALPATH, pack('Na*', strlen($dir), $dir))) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        switch ($this->packet_type) {
            case NET_SFTP_NAME:
                // although SSH_FXP_NAME is implemented differently in SFTPv3 than it is in SFTPv4+, the following
                // should work on all SFTP versions since the only part of the SSH_FXP_NAME packet the following looks
                // at is the first part and that part is defined the same in SFTP versions 3 through 6.
                $this->_string_shift($response, 4); // skip over the count - it should be 1, anyway
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $realpath = $this->_string_shift($response, $length);
                // the following is SFTPv3 only code.  see Net_SFTP::_parseLongname() for more information.
                // per the above comment, this is a shot in the dark that, on most servers, won't help us in determining
                // the file type for Net_SFTP::stat() and Net_SFTP::lstat() but it's worth a shot.
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->fileType = $this->_parseLongname($this->_string_shift($response, $length));
                break;
            case NET_SFTP_STATUS:
                extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                return false;
            default:
                user_error('Expected SSH_FXP_NAME or SSH_FXP_STATUS', E_USER_NOTICE);
                return false;
        }

        // if $this->pwd isn't set than the only thing $realpath could be is for '.', which is pretty much guaranteed to
        // be a bonafide directory
        return $realpath . '/' . $file;
    }

    /**
     * Changes the current directory
     *
     * @param String $dir
     * @return Boolean
     * @access public
     */
    function chdir($dir)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        if ($dir[strlen($dir) - 1] != '/') {
            $dir.= '/';
        }

        // confirm that $dir is, in fact, a valid directory
        if ($this->_is_dir($dir)) {
            $this->pwd = $dir;
            return true;
        }

        $dir = $this->_realpath($dir, false);

        if ($this->_is_dir($dir)) {
            $this->pwd = $dir;
            return true;
        }

        if (!$this->_send_sftp_packet(NET_SFTP_OPENDIR, pack('Na*', strlen($dir), $dir))) {
            return false;
        }

        // see Net_SFTP::nlist() for a more thorough explanation of the following
        $response = $this->_get_sftp_packet();
        switch ($this->packet_type) {
            case NET_SFTP_HANDLE:
                $handle = substr($response, 4);
                break;
            case NET_SFTP_STATUS:
                extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                return false;
            default:
                user_error('Expected SSH_FXP_HANDLE or SSH_FXP_STATUS', E_USER_NOTICE);
                return false;
        }

        if (!$this->_send_sftp_packet(NET_SFTP_CLOSE, pack('Na*', strlen($handle), $handle))) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Nstatus', $this->_string_shift($response, 4)));
        if ($status != NET_SFTP_STATUS_OK) {
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
            return false;
        }

        $this->_save_dir($dir);

        $this->pwd = $dir;
        return true;
    }

    /**
     * Returns a list of files in the given directory
     *
     * @param optional String $dir
     * @return Mixed
     * @access public
     */
    function nlist($dir = '.')
    {
        return $this->_list($dir, false);
    }

    /**
     * Returns a detailed list of files in the given directory
     *
     * @param optional String $dir
     * @return Mixed
     * @access public
     */
    function rawlist($dir = '.')
    {
        return $this->_list($dir, true);
    }

    /**
     * Reads a list, be it detailed or not, of files in the given directory
     *
     * @param optional String $dir
     * @return Mixed
     * @access private
     */
    function _list($dir, $raw = true, $realpath = true)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $dir = $this->_realpath($dir . '/');
        if ($dir === false) {
            return false;
        }

        // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.1.2
        if (!$this->_send_sftp_packet(NET_SFTP_OPENDIR, pack('Na*', strlen($dir), $dir))) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        switch ($this->packet_type) {
            case NET_SFTP_HANDLE:
                // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-9.2
                // since 'handle' is the last field in the SSH_FXP_HANDLE packet, we'll just remove the first four bytes that
                // represent the length of the string and leave it at that
                $handle = substr($response, 4);
                break;
            case NET_SFTP_STATUS:
                // presumably SSH_FX_NO_SUCH_FILE or SSH_FX_PERMISSION_DENIED
                extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                return false;
            default:
                user_error('Expected SSH_FXP_HANDLE or SSH_FXP_STATUS', E_USER_NOTICE);
                return false;
        }

        $this->_save_dir($dir);

        $contents = array();
        while (true) {
            // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.2.2
            // why multiple SSH_FXP_READDIR packets would be sent when the response to a single one can span arbitrarily many
            // SSH_MSG_CHANNEL_DATA messages is not known to me.
            if (!$this->_send_sftp_packet(NET_SFTP_READDIR, pack('Na*', strlen($handle), $handle))) {
                return false;
            }

            $response = $this->_get_sftp_packet();
            switch ($this->packet_type) {
                case NET_SFTP_NAME:
                    extract(unpack('Ncount', $this->_string_shift($response, 4)));
                    for ($i = 0; $i < $count; $i++) {
                        extract(unpack('Nlength', $this->_string_shift($response, 4)));
                        $shortname = $this->_string_shift($response, $length);
                        extract(unpack('Nlength', $this->_string_shift($response, 4)));
                        $longname = $this->_string_shift($response, $length);
                        $attributes = $this->_parseAttributes($response); // we also don't care about the attributes
                        if (!$raw) {
                            $contents[] = $shortname;
                        } else {
                            $contents[$shortname] = $attributes;
                            $fileType = $this->_parseLongname($longname);
                            if ($fileType) {
                                if ($fileType == NET_SFTP_TYPE_DIRECTORY && ($shortname != '.' && $shortname != '..')) {
                                    $this->_save_dir($dir . '/' . $shortname);
                                }
                                $contents[$shortname]['type'] = $fileType;
                            }
                        }
                        // SFTPv6 has an optional boolean end-of-list field, but we'll ignore that, since the
                        // final SSH_FXP_STATUS packet should tell us that, already.
                    }
                    break;
                case NET_SFTP_STATUS:
                    extract(unpack('Nstatus', $this->_string_shift($response, 4)));
                    if ($status != NET_SFTP_STATUS_EOF) {
                        extract(unpack('Nlength', $this->_string_shift($response, 4)));
                        $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                        return false;
                    }
                    break 2;
                default:
                    user_error('Expected SSH_FXP_NAME or SSH_FXP_STATUS', E_USER_NOTICE);
                    return false;
            }
        }

        if (!$this->_send_sftp_packet(NET_SFTP_CLOSE, pack('Na*', strlen($handle), $handle))) {
            return false;
        }

        // "The client MUST release all resources associated with the handle regardless of the status."
        //  -- http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.1.3
        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Nstatus', $this->_string_shift($response, 4)));
        if ($status != NET_SFTP_STATUS_OK) {
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
            return false;
        }

        return $contents;
    }

    /**
     * Returns the file size, in bytes, or false, on failure
     *
     * Files larger than 4GB will show up as being exactly 4GB.
     *
     * @param String $filename
     * @return Mixed
     * @access public
     */
    function size($filename)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $filename = $this->_realpath($filename);
        if ($filename === false) {
            return false;
        }

        return $this->_size($filename);
    }

    /**
     * Save directories to cache
     *
     * @param String $dir
     * @access private
     */
    function _save_dir($dir)
    {
        // preg_replace('#^/|/(?=/)|/$#', '', $dir) == str_replace('//', '/', trim($dir, '/'))
        $dirs = explode('/', preg_replace('#^/|/(?=/)|/$#', '', $dir));

        $temp = &$this->dirs;
        foreach ($dirs as $dir) {
            if (!isset($temp[$dir])) {
                $temp[$dir] = array();
            }
            $temp = &$temp[$dir];
        }
    }

    /**
     * Remove directories from cache
     *
     * @param String $dir
     * @access private
     */
    function _remove_dir($dir)
    {
        $dirs = explode('/', preg_replace('#^/|/(?=/)|/$#', '', $dir));

        $temp = &$this->dirs;
        foreach ($dirs as $dir) {
            if ($dir == end($dirs)) {
                unset($temp[$dir]);
                return true;
            }
            if (!isset($temp[$dir])) {
                return false;
            }
            $temp = &$temp[$dir];
        }
    }

    /**
     * Checks cache for directory
     *
     * @param String $dir
     * @access private
     */
    function _is_dir($dir)
    {
        $dirs = explode('/', preg_replace('#^/|/(?=/)|/$#', '', $dir));

        $temp = &$this->dirs;
        foreach ($dirs as $dir) {
            if (!isset($temp[$dir])) {
                return false;
            }
            $temp = &$temp[$dir];
        }
    }

    /**
     * Returns general information about a file.
     *
     * Returns an array on success and false otherwise.
     *
     * @param String $filename
     * @return Mixed
     * @access public
     */
    function stat($filename)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $filename = $this->_realpath($filename);
        if ($filename === false) {
            return false;
        }

        $stat = $this->_stat($filename, NET_SFTP_STAT);

        $pwd = $this->pwd;
        $stat['type'] = $this->chdir($filename) ?
            NET_SFTP_TYPE_DIRECTORY :
            NET_SFTP_TYPE_REGULAR;
        $this->pwd = $pwd;

        return $stat;
    }

    /**
     * Returns general information about a file or symbolic link.
     *
     * Returns an array on success and false otherwise.
     *
     * @param String $filename
     * @return Mixed
     * @access public
     */
    function lstat($filename)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $filename = $this->_realpath($filename);
        if ($filename === false) {
            return false;
        }

        $lstat = $this->_stat($filename, NET_SFTP_LSTAT);
        $stat = $this->_stat($filename, NET_SFTP_STAT);

        if ($lstat != $stat) {
            return array_merge($lstat, array('type' => NET_SFTP_TYPE_SYMLINK));
        }

        $pwd = $this->pwd;
        $lstat['type'] = $this->chdir($filename) ?
            NET_SFTP_TYPE_DIRECTORY :
            NET_SFTP_TYPE_REGULAR;
        $this->pwd = $pwd;

        return $lstat;
    }

    /**
     * Returns general information about a file or symbolic link
     *
     * Determines information without calling Net_SFTP::_realpath().
     * The second parameter can be either NET_SFTP_STAT or NET_SFTP_LSTAT.
     *
     * @param String $filename
     * @param Integer $type
     * @return Mixed
     * @access private
     */
    function _stat($filename, $type)
    {
        // SFTPv4+ adds an additional 32-bit integer field - flags - to the following:
        $packet = pack('Na*', strlen($filename), $filename);
        if (!$this->_send_sftp_packet($type, $packet)) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        switch ($this->packet_type) {
            case NET_SFTP_ATTRS:
                $attributes = $this->_parseAttributes($response);
                if ($this->fileType) {
                    $attributes['type'] = $this->fileType;
                }
                return $attributes;
            case NET_SFTP_STATUS:
                extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                return false;
        }

        user_error('Expected SSH_FXP_ATTRS or SSH_FXP_STATUS', E_USER_NOTICE);
        return false;
    }

    /**
     * Attempt to identify the file type
     *
     * @param String $path
     * @param Array $stat
     * @param Array $lstat
     * @return Integer
     * @access private
     */
    function _identify_type($path, $stat1, $stat2)
    {
        $stat1 = $this->_stat($path, $stat1);
        $stat2 = $this->_stat($path, $stat2);

        if ($stat1 != $stat2) {
            return array_merge($stat1, array('type' => NET_SFTP_TYPE_SYMLINK));
        }

        $pwd = $this->pwd;
        $stat1['type'] = $this->chdir($path) ?
            NET_SFTP_TYPE_DIRECTORY :
            NET_SFTP_TYPE_REGULAR;
        $this->pwd = $pwd;

        return $stat1;
    }

    /**
     * Returns the file size, in bytes, or false, on failure
     *
     * Determines the size without calling Net_SFTP::_realpath()
     *
     * @param String $filename
     * @return Mixed
     * @access private
     */
    function _size($filename)
    {
        $result = $this->_stat($filename, NET_SFTP_LSTAT);
        if ($result === false) {
            return false;
        }
        return isset($result['size']) ? $result['size'] : -1;
    }

    /**
     * Set permissions on a file.
     *
     * Returns the new file permissions on success or FALSE on error.
     *
     * @param Integer $mode
     * @param String $filename
     * @return Mixed
     * @access public
     */
    function chmod($mode, $filename, $recursive = false)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $filename = $this->_realpath($filename);
        if ($filename === false) {
            return false;
        }

        if ($recursive) {
            $i = 0;
            $result = $this->_chmod_recursive($mode, $filename, $i);
            $this->_read_put_responses($i);
            return $result;
        }

        // SFTPv4+ has an additional byte field - type - that would need to be sent, as well. setting it to
        // SSH_FILEXFER_TYPE_UNKNOWN might work. if not, we'd have to do an SSH_FXP_STAT before doing an SSH_FXP_SETSTAT.
        $attr = pack('N2', NET_SFTP_ATTR_PERMISSIONS, $mode & 07777);
        if (!$this->_send_sftp_packet(NET_SFTP_SETSTAT, pack('Na*a*', strlen($filename), $filename, $attr))) {
            return false;
        }

        /*
         "Because some systems must use separate system calls to set various attributes, it is possible that a failure 
          response will be returned, but yet some of the attributes may be have been successfully modified.  If possible,
          servers SHOULD avoid this situation; however, clients MUST be aware that this is possible."

          -- http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.6
        */
        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Nstatus', $this->_string_shift($response, 4)));
        if ($status != NET_SFTP_STATUS_OK) {
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
        }

        // rather than return what the permissions *should* be, we'll return what they actually are.  this will also
        // tell us if the file actually exists.
        // incidentally, SFTPv4+ adds an additional 32-bit integer field - flags - to the following:
        $packet = pack('Na*', strlen($filename), $filename);
        if (!$this->_send_sftp_packet(NET_SFTP_STAT, $packet)) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        switch ($this->packet_type) {
            case NET_SFTP_ATTRS:
                $attrs = $this->_parseAttributes($response);
                return $attrs['permissions'];
            case NET_SFTP_STATUS:
                extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                return false;
        }

        user_error('Expected SSH_FXP_ATTRS or SSH_FXP_STATUS', E_USER_NOTICE);
        return false;
    }

    /**
     * Recursively chmods directories on the SFTP server
     *
     * Minimizes directory lookups and SSH_FXP_STATUS requests for speed.
     *
     * @param Integer $mode
     * @param String $filename
     * @return Boolean
     * @access private
     */
    function _chmod_recursive($mode, $path, &$i)
    {
        if (!$this->_read_put_responses($i)) {
            return false;
        }
        $i = 0;
        $entries = $this->_list($path, true, false);

        if ($entries === false) {
            return $this->chmod($mode, $path);
        }

        // presumably $entries will never be empty because it'll always have . and ..

        foreach ($entries as $filename=>$props) {
            if ($filename == '.' || $filename == '..') {
                continue;
            }

            if (!isset($props['type'])) {
                return false;
            }

            $temp = $path . '/' . $filename;
            if ($props['type'] == NET_SFTP_TYPE_DIRECTORY) {
                if (!$this->_chmod_recursive($mode, $temp, $i)) {
                    return false;
                }
            } else {
                $attr = pack('N2', NET_SFTP_ATTR_PERMISSIONS, $mode & 07777);
                if (!$this->_send_sftp_packet(NET_SFTP_SETSTAT, pack('Na*a*', strlen($temp), $temp, $attr))) {
                    return false;
                }

                $i++;

                if ($i >= 50) {
                    if (!$this->_read_put_responses($i)) {
                        return false;
                    }
                    $i = 0;
                }
            }
        }

        $attr = pack('N2', NET_SFTP_ATTR_PERMISSIONS, $mode & 07777);
        if (!$this->_send_sftp_packet(NET_SFTP_SETSTAT, pack('Na*a*', strlen($path), $path, $attr))) {
            return false;
        }

        $i++;

        if ($i >= 50) {
            if (!$this->_read_put_responses($i)) {
                return false;
            }
            $i = 0;
        }

        return true;
    }

    /**
     * Creates a directory.
     *
     * @param String $dir
     * @return Boolean
     * @access public
     */
    function mkdir($dir)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $dir = $this->_realpath(rtrim($dir, '/'));
        if ($dir === false) {
            return false;
        }

        // by not providing any permissions, hopefully the server will use the logged in users umask - their 
        // default permissions.
        if (!$this->_send_sftp_packet(NET_SFTP_MKDIR, pack('Na*N', strlen($dir), $dir, 0))) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Nstatus', $this->_string_shift($response, 4)));
        if ($status != NET_SFTP_STATUS_OK) {
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
            return false;
        }

        $this->_save_dir($dir);

        return true;
    }

    /**
     * Removes a directory.
     *
     * @param String $dir
     * @return Boolean
     * @access public
     */
    function rmdir($dir)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $dir = $this->_realpath($dir);
        if ($dir === false) {
            return false;
        }

        if (!$this->_send_sftp_packet(NET_SFTP_RMDIR, pack('Na*', strlen($dir), $dir))) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Nstatus', $this->_string_shift($response, 4)));
        if ($status != NET_SFTP_STATUS_OK) {
            // presumably SSH_FX_NO_SUCH_FILE or SSH_FX_PERMISSION_DENIED?
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
            return false;
        }

        $this->_remove_dir($dir);

        return true;
    }

    /**
     * Uploads a file to the SFTP server.
     *
     * By default, Net_SFTP::put() does not read from the local filesystem.  $data is dumped directly into $remote_file.
     * So, for example, if you set $data to 'filename.ext' and then do Net_SFTP::get(), you will get a file, twelve bytes
     * long, containing 'filename.ext' as its contents.
     *
     * Setting $mode to NET_SFTP_LOCAL_FILE will change the above behavior.  With NET_SFTP_LOCAL_FILE, $remote_file will 
     * contain as many bytes as filename.ext does on your local filesystem.  If your filename.ext is 1MB then that is how
     * large $remote_file will be, as well.
     *
     * Currently, only binary mode is supported.  As such, if the line endings need to be adjusted, you will need to take
     * care of that, yourself.
     *
     * @param String $remote_file
     * @param String $data
     * @param optional Integer $mode
     * @return Boolean
     * @access public
     * @internal ASCII mode for SFTPv4/5/6 can be supported by adding a new function - Net_SFTP::setMode().
     */
    function put($remote_file, $data, $mode = NET_SFTP_STRING)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $remote_file = $this->_realpath($remote_file);
        if ($remote_file === false) {
            return false;
        }

        $flags = NET_SFTP_OPEN_WRITE | NET_SFTP_OPEN_CREATE;
        // according to the SFTP specs, NET_SFTP_OPEN_APPEND should "force all writes to append data at the end of the file."
        // in practice, it doesn't seem to do that.
        //$flags|= ($mode & NET_SFTP_RESUME) ? NET_SFTP_OPEN_APPEND : NET_SFTP_OPEN_TRUNCATE;

        // if NET_SFTP_OPEN_APPEND worked as it should the following (up until the -----------) wouldn't be necessary
        $offset = 0;
        if ($mode & NET_SFTP_RESUME) {
            $size = $this->_size($remote_file);
            $offset = $size !== false ? $size : 0;
        } else {
            $flags|= NET_SFTP_OPEN_TRUNCATE;
        }
        // --------------

        $packet = pack('Na*N2', strlen($remote_file), $remote_file, $flags, 0);
        if (!$this->_send_sftp_packet(NET_SFTP_OPEN, $packet)) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        switch ($this->packet_type) {
            case NET_SFTP_HANDLE:
                $handle = substr($response, 4);
                break;
            case NET_SFTP_STATUS:
                extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                return false;
            default:
                user_error('Expected SSH_FXP_HANDLE or SSH_FXP_STATUS', E_USER_NOTICE);
                return false;
        }

        $initialize = true;

        // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.2.3
        if ($mode & NET_SFTP_LOCAL_FILE) {
            if (!is_file($data)) {
                user_error("$data is not a valid file", E_USER_NOTICE);
                return false;
            }
            $fp = @fopen($data, 'rb');
            if (!$fp) {
                return false;
            }
            $size = filesize($data);
        } else {
            $size = strlen($data);
        }

        $sent = 0;
        $size = $size < 0 ? ($size & 0x7FFFFFFF) + 0x80000000 : $size;

        $sftp_packet_size = 4096; // PuTTY uses 4096
        $i = 0;
        while ($sent < $size) {
            $temp = $mode & NET_SFTP_LOCAL_FILE ? fread($fp, $sftp_packet_size) : $this->_string_shift($data, $sftp_packet_size);
            $packet = pack('Na*N3a*', strlen($handle), $handle, 0, $offset + $sent, strlen($temp), $temp);
            if (!$this->_send_sftp_packet(NET_SFTP_WRITE, $packet)) {
                fclose($fp);
                return false;
            }
            $sent+= strlen($temp);

            $i++;

            if ($i == 50) {
                if (!$this->_read_put_responses($i)) {
                    $i = 0;
                    break;
                }
                $i = 0;
            }
        }

        if (!$this->_read_put_responses($i)) {
            return false;
        }

        if ($mode & NET_SFTP_LOCAL_FILE) {
            fclose($fp);
        }

        if (!$this->_send_sftp_packet(NET_SFTP_CLOSE, pack('Na*', strlen($handle), $handle))) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Nstatus', $this->_string_shift($response, 4)));
        if ($status != NET_SFTP_STATUS_OK) {
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
            return false;
        }

        return true;
    }

    /**
     * Reads multiple successive SSH_FXP_WRITE responses
     *
     * Sending an SSH_FXP_WRITE packet and immediately reading its response isn't as efficient as blindly sending out $i
     * SSH_FXP_WRITEs, in succession, and then reading $i responses.
     *
     * @param Integer $i
     * @return Boolean
     * @access private
     */
    function _read_put_responses($i)
    {
        while ($i--) {
            $response = $this->_get_sftp_packet();
            if ($this->packet_type != NET_SFTP_STATUS) {
                user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
                return false;
            }

            extract(unpack('Nstatus', $this->_string_shift($response, 4)));
            if ($status != NET_SFTP_STATUS_OK) {
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                break;
            }
        }

        return $i < 0;
    }

    /**
     * Downloads a file from the SFTP server.
     *
     * Returns a string containing the contents of $remote_file if $local_file is left undefined or a boolean false if
     * the operation was unsuccessful.  If $local_file is defined, returns true or false depending on the success of the
     * operation
     *
     * @param String $remote_file
     * @param optional String $local_file
     * @return Mixed
     * @access public
     */
    function get($remote_file, $local_file = false)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $remote_file = $this->_realpath($remote_file);
        if ($remote_file === false) {
            return false;
        }

        $packet = pack('Na*N2', strlen($remote_file), $remote_file, NET_SFTP_OPEN_READ, 0);
        if (!$this->_send_sftp_packet(NET_SFTP_OPEN, $packet)) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        switch ($this->packet_type) {
            case NET_SFTP_HANDLE:
                $handle = substr($response, 4);
                break;
            case NET_SFTP_STATUS: // presumably SSH_FX_NO_SUCH_FILE or SSH_FX_PERMISSION_DENIED
                extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                return false;
            default:
                user_error('Expected SSH_FXP_HANDLE or SSH_FXP_STATUS', E_USER_NOTICE);
                return false;
        }

        if ($local_file !== false) {
            $fp = fopen($local_file, 'wb');
            if (!$fp) {
                return false;
            }
        } else {
            $content = '';
        }

        $read = 0;
        while (true) {
            $packet = pack('Na*N3', strlen($handle), $handle, 0, $read, 1 << 20);
            if (!$this->_send_sftp_packet(NET_SFTP_READ, $packet)) {
                if ($local_file !== false) {
                    fclose($fp);
                }
                return false;
            }

            $response = $this->_get_sftp_packet();
            switch ($this->packet_type) {
                case NET_SFTP_DATA:
                    $temp = substr($response, 4);
                    $read+= strlen($temp);
                    if ($local_file === false) {
                        $content.= $temp;
                    } else {
                        fputs($fp, $temp);
                    }
                    break;
                case NET_SFTP_STATUS:
                    extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
                    $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
                    break 2;
                default:
                    user_error('Expected SSH_FXP_DATA or SSH_FXP_STATUS', E_USER_NOTICE);
                    if ($local_file !== false) {
                        fclose($fp);
                    }
                    return false;
            }
        }

        if ($local_file !== false) {
            fclose($fp);
        }

        if (!$this->_send_sftp_packet(NET_SFTP_CLOSE, pack('Na*', strlen($handle), $handle))) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Nstatus/Nlength', $this->_string_shift($response, 8)));
        $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);

        // check the status from the NET_SFTP_STATUS case in the above switch after the file has been closed
        if ($status != NET_SFTP_STATUS_OK) {
            return false;
        }

        if (isset($content)) {
            return $content;
        }

        return true;
    }

    /**
     * Deletes a file on the SFTP server.
     *
     * @param String $path
     * @param Boolean $recursive
     * @return Boolean
     * @access public
     */
    function delete($path, $recursive = true)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $path = $this->_realpath($path);
        if ($path === false) {
            return false;
        }

        // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.3
        if (!$this->_send_sftp_packet(NET_SFTP_REMOVE, pack('Na*', strlen($path), $path))) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        // if $status isn't SSH_FX_OK it's probably SSH_FX_NO_SUCH_FILE or SSH_FX_PERMISSION_DENIED
        extract(unpack('Nstatus', $this->_string_shift($response, 4)));
        if ($status != NET_SFTP_STATUS_OK) {
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
            if (!$recursive) {
                return false;
            }
            $i = 0;
            $result = $this->_delete_recursive($path, $i);
            $this->_read_put_responses($i);
            return $result;
        }

        return true;
    }

    /**
     * Recursively deletes directories on the SFTP server
     *
     * Minimizes directory lookups and SSH_FXP_STATUS requests for speed.
     *
     * @param String $path
     * @param Integer $i
     * @return Boolean
     * @access private
     */
    function _delete_recursive($path, &$i)
    {
        if (!$this->_read_put_responses($i)) {
            return false;
        }
        $i = 0;
        $entries = $this->_list($path, true, false);

        // presumably $entries will never be empty because it'll always have . and ..

        foreach ($entries as $filename=>$props) {
            if ($filename == '.' || $filename == '..') {
                continue;
            }

            if (!isset($props['type'])) {
                return false;
            }

            $temp = $path . '/' . $filename;
            if ($props['type'] == NET_SFTP_TYPE_DIRECTORY) {
                if (!$this->_delete_recursive($temp, $i)) {
                    return false;
                }
            } else {
                if (!$this->_send_sftp_packet(NET_SFTP_REMOVE, pack('Na*', strlen($temp), $temp))) {
                    return false;
                }

                $i++;

                if ($i >= 50) {
                    if (!$this->_read_put_responses($i)) {
                        return false;
                    }
                    $i = 0;
                }
            }
        }

        if (!$this->_send_sftp_packet(NET_SFTP_RMDIR, pack('Na*', strlen($path), $path))) {
            return false;
        }
        $this->_remove_dir($path);

        $i++;

        if ($i >= 50) {
            if (!$this->_read_put_responses($i)) {
                return false;
            }
            $i = 0;
        }

        return true;
    }

    /**
     * Renames a file or a directory on the SFTP server
     *
     * @param String $oldname
     * @param String $newname
     * @return Boolean
     * @access public
     */
    function rename($oldname, $newname)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        $oldname = $this->_realpath($oldname);
        $newname = $this->_realpath($newname);
        if ($oldname === false || $newname === false) {
            return false;
        }

        // http://tools.ietf.org/html/draft-ietf-secsh-filexfer-13#section-8.3
        $packet = pack('Na*Na*', strlen($oldname), $oldname, strlen($newname), $newname);
        if (!$this->_send_sftp_packet(NET_SFTP_RENAME, $packet)) {
            return false;
        }

        $response = $this->_get_sftp_packet();
        if ($this->packet_type != NET_SFTP_STATUS) {
            user_error('Expected SSH_FXP_STATUS', E_USER_NOTICE);
            return false;
        }

        // if $status isn't SSH_FX_OK it's probably SSH_FX_NO_SUCH_FILE or SSH_FX_PERMISSION_DENIED
        extract(unpack('Nstatus', $this->_string_shift($response, 4)));
        if ($status != NET_SFTP_STATUS_OK) {
            extract(unpack('Nlength', $this->_string_shift($response, 4)));
            $this->sftp_errors[] = $this->status_codes[$status] . ': ' . $this->_string_shift($response, $length);
            return false;
        }

        return true;
    }

    /**
     * Parse Attributes
     *
     * See '7.  File Attributes' of draft-ietf-secsh-filexfer-13 for more info.
     *
     * @param String $response
     * @return Array
     * @access private
     */
    function _parseAttributes(&$response)
    {
        $attr = array();
        extract(unpack('Nflags', $this->_string_shift($response, 4)));
        // SFTPv4+ have a type field (a byte) that follows the above flag field
        foreach ($this->attributes as $key => $value) {
            switch ($flags & $key) {
                case NET_SFTP_ATTR_SIZE: // 0x00000001
                    // size is represented by a 64-bit integer, so we perhaps ought to be doing the following:
                    // $attr['size'] = new Math_BigInteger($this->_string_shift($response, 8), 256);
                    // of course, you shouldn't be using Net_SFTP to transfer files that are in excess of 4GB
                    // (0xFFFFFFFF bytes), anyway.  as such, we'll just represent all file sizes that are bigger than
                    // 4GB as being 4GB.
                    extract(unpack('Nupper/Nsize', $this->_string_shift($response, 8)));
                    if ($upper) {
                        $attr['size'] = 0xFFFFFFFF;
                    } else {
                        $attr['size'] = $size < 0 ? ($size & 0x7FFFFFFF) + 0x80000000 : $size;
                    }
                    break;
                case NET_SFTP_ATTR_UIDGID: // 0x00000002 (SFTPv3 only)
                    $attr+= unpack('Nuid/Ngid', $this->_string_shift($response, 8));
                    break;
                case NET_SFTP_ATTR_PERMISSIONS: // 0x00000004
                    $attr+= unpack('Npermissions', $this->_string_shift($response, 4));
                    break;
                case NET_SFTP_ATTR_ACCESSTIME: // 0x00000008
                    $attr+= unpack('Natime/Nmtime', $this->_string_shift($response, 8));
                    break;
                case NET_SFTP_ATTR_EXTENDED: // 0x80000000
                    extract(unpack('Ncount', $this->_string_shift($response, 4)));
                    for ($i = 0; $i < $count; $i++) {
                        extract(unpack('Nlength', $this->_string_shift($response, 4)));
                        $key = $this->_string_shift($response, $length);
                        extract(unpack('Nlength', $this->_string_shift($response, 4)));
                        $attr[$key] = $this->_string_shift($response, $length);                        
                    }
            }
        }
        return $attr;
    }

    /**
     * Parse Longname
     *
     * SFTPv3 doesn't provide any easy way of identifying a file type.  You could try to open
     * a file as a directory and see if an error is returned or you could try to parse the
     * SFTPv3-specific longname field of the SSH_FXP_NAME packet.  That's what this function does.
     * The result is returned using the
     * {@link http://tools.ietf.org/html/draft-ietf-secsh-filexfer-04#section-5.2 SFTPv4 type constants}.
     *
     * If the longname is in an unrecognized format bool(false) is returned.
     *
     * @param String $longname
     * @return Mixed
     * @access private
     */
    function _parseLongname($longname)
    {
        // http://en.wikipedia.org/wiki/Unix_file_types
        // http://en.wikipedia.org/wiki/Filesystem_permissions#Notation_of_traditional_Unix_permissions
        if (preg_match('#^[^/]([r-][w-][xstST-]){3}#', $longname)) {
            switch ($longname[0]) {
                case '-':
                    return NET_SFTP_TYPE_REGULAR;
                case 'd':
                    return NET_SFTP_TYPE_DIRECTORY;
                case 'l':
                    return NET_SFTP_TYPE_SYMLINK;
                default:
                    return NET_SFTP_TYPE_SPECIAL;
            }
        }

        return false;
    }

    /**
     * Sends SFTP Packets
     *
     * See '6. General Packet Format' of draft-ietf-secsh-filexfer-13 for more info.
     *
     * @param Integer $type
     * @param String $data
     * @see Net_SFTP::_get_sftp_packet()
     * @see Net_SSH2::_send_channel_packet()
     * @return Boolean
     * @access private
     */
    function _send_sftp_packet($type, $data)
    {
        $packet = $this->request_id !== false ?
            pack('NCNa*', strlen($data) + 5, $type, $this->request_id, $data) :
            pack('NCa*',  strlen($data) + 1, $type, $data);

        $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838
        $result = $this->_send_channel_packet(NET_SFTP_CHANNEL, $packet);
        $stop = strtok(microtime(), ' ') + strtok('');

        if (defined('NET_SFTP_LOGGING')) {
            $packet_type = '-> ' . $this->packet_types[$type] . 
                           ' (' . round($stop - $start, 4) . 's)';
            if (NET_SFTP_LOGGING == NET_SFTP_LOG_REALTIME) {
                echo "<pre>\r\n" . $this->_format_log(array($data), array($packet_type)) . "\r\n</pre>\r\n";
                flush();
                ob_flush();
            } else {
                $this->packet_type_log[] = $packet_type;
                if (NET_SFTP_LOGGING == NET_SFTP_LOG_COMPLEX) {
                    $this->packet_log[] = $data;
                }
            }
        }

        return $result;
    }

    /**
     * Receives SFTP Packets
     *
     * See '6. General Packet Format' of draft-ietf-secsh-filexfer-13 for more info.
     *
     * Incidentally, the number of SSH_MSG_CHANNEL_DATA messages has no bearing on the number of SFTP packets present.
     * There can be one SSH_MSG_CHANNEL_DATA messages containing two SFTP packets or there can be two SSH_MSG_CHANNEL_DATA
     * messages containing one SFTP packet.
     *
     * @see Net_SFTP::_send_sftp_packet()
     * @return String
     * @access private
     */
    function _get_sftp_packet()
    {
        $this->curTimeout = false;

        $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838

        // SFTP packet length
        while (strlen($this->packet_buffer) < 4) {
            $temp = $this->_get_channel_packet(NET_SFTP_CHANNEL);
            if (is_bool($temp)) {
                $this->packet_type = false;
                $this->packet_buffer = '';
                return false;
            }
            $this->packet_buffer.= $temp;
        }
        extract(unpack('Nlength', $this->_string_shift($this->packet_buffer, 4)));
        $tempLength = $length;
        $tempLength-= strlen($this->packet_buffer);

        // SFTP packet type and data payload
        while ($tempLength > 0) {
            $temp = $this->_get_channel_packet(NET_SFTP_CHANNEL);
            if (is_bool($temp)) {
                $this->packet_type = false;
                $this->packet_buffer = '';
                return false;
            }
            $this->packet_buffer.= $temp;
            $tempLength-= strlen($temp);
        }

        $stop = strtok(microtime(), ' ') + strtok('');

        $this->packet_type = ord($this->_string_shift($this->packet_buffer));

        if ($this->request_id !== false) {
            $this->_string_shift($this->packet_buffer, 4); // remove the request id
            $length-= 5; // account for the request id and the packet type
        } else {
            $length-= 1; // account for the packet type
        }

        $packet = $this->_string_shift($this->packet_buffer, $length);

        if (defined('NET_SFTP_LOGGING')) {
            $packet_type = '<- ' . $this->packet_types[$this->packet_type] . 
                           ' (' . round($stop - $start, 4) . 's)';
            if (NET_SFTP_LOGGING == NET_SFTP_LOG_REALTIME) {
                echo "<pre>\r\n" . $this->_format_log(array($packet), array($packet_type)) . "\r\n</pre>\r\n";
                flush();
                ob_flush();
            } else {
                $this->packet_type_log[] = $packet_type;
                if (NET_SFTP_LOGGING == NET_SFTP_LOG_COMPLEX) {
                    $this->packet_log[] = $packet;
                }
            }
        }

        return $packet;
    }

    /**
     * Returns a log of the packets that have been sent and received.
     *
     * Returns a string if NET_SFTP_LOGGING == NET_SFTP_LOG_COMPLEX, an array if NET_SFTP_LOGGING == NET_SFTP_LOG_SIMPLE and false if !defined('NET_SFTP_LOGGING')
     *
     * @access public
     * @return String or Array
     */
    function getSFTPLog()
    {
        if (!defined('NET_SFTP_LOGGING')) {
            return false;
        }

        switch (NET_SFTP_LOGGING) {
            case NET_SFTP_LOG_COMPLEX:
                return $this->_format_log($this->packet_log, $this->packet_type_log);
                break;
            //case NET_SFTP_LOG_SIMPLE:
            default:
                return $this->packet_type_log;
        }
    }

    /**
     * Returns all errors
     *
     * @return String
     * @access public
     */
    function getSFTPErrors()
    {
        return $this->sftp_errors;
    }

    /**
     * Returns the last error
     *
     * @return String
     * @access public
     */
    function getLastSFTPError()
    {
        return count($this->sftp_errors) ? $this->sftp_errors[count($this->sftp_errors) - 1] : '';
    }

    /**
     * Get supported SFTP versions
     *
     * @return Array
     * @access public
     */
    function getSupportedVersions()
    {
        $temp = array('version' => $this->version);
        if (isset($this->extensions['versions'])) {
            $temp['extensions'] = $this->extensions['versions'];
        }
        return $temp;
    }

    /**
     * Disconnect
     *
     * @param Integer $reason
     * @return Boolean
     * @access private
     */
    function _disconnect($reason)
    {
        $this->pwd = false;
        parent::_disconnect($reason);
    }
}

<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Pure-PHP implementation of SSHv1.
 *
 * PHP versions 4 and 5
 *
 * Here's a short example of how to use this library:
 * <code>
 * <?php
 *    include('Net/SSH1.php');
 *
 *    $ssh = new Net_SSH1('www.domain.tld');
 *    if (!$ssh->login('username', 'password')) {
 *        exit('Login Failed');
 *    }
 *
 *    echo $ssh->exec('ls -la');
 * ?>
 * </code>
 *
 * Here's another short example:
 * <code>
 * <?php
 *    include('Net/SSH1.php');
 *
 *    $ssh = new Net_SSH1('www.domain.tld');
 *    if (!$ssh->login('username', 'password')) {
 *        exit('Login Failed');
 *    }
 *
 *    echo $ssh->read('username@username:~$');
 *    $ssh->write("ls -la\n");
 *    echo $ssh->read('username@username:~$');
 * ?>
 * </code>
 *
 * More information on the SSHv1 specification can be found by reading 
 * {@link http://www.snailbook.com/docs/protocol-1.5.txt protocol-1.5.txt}.
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category   Net
 * @package    Net_SSH1
 * @author     Jim Wigginton <terrafrost@php.net>
 * @copyright  MMVII Jim Wigginton
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    $Id: SSH1.php,v 1.15 2010/03/22 22:01:38 terrafrost Exp $
 * @link       http://phpseclib.sourceforge.net
 */

/**
 * Include Math_BigInteger
 *
 * Used to do RSA encryption.
 */
if (!class_exists('Math_BigInteger')) {
    require_once('Math/BigInteger.php');
}

/**
 * Include Crypt_Null
 */
//require_once('Crypt/Null.php');

/**
 * Include Crypt_DES
 */
if (!class_exists('Crypt_DES')) {
    require_once('Crypt/DES.php');
}

/**
 * Include Crypt_TripleDES
 */
if (!class_exists('Crypt_TripleDES')) {
    require_once('Crypt/TripleDES.php');
}

/**
 * Include Crypt_RC4
 */
if (!class_exists('Crypt_RC4')) {
    require_once('Crypt/RC4.php');
}

/**
 * Include Crypt_Random
 */
// the class_exists() will only be called if the crypt_random function hasn't been defined and
// will trigger a call to __autoload() if you're wanting to auto-load classes
// call function_exists() a second time to stop the require_once from being called outside
// of the auto loader
if (!function_exists('crypt_random') && !class_exists('Crypt_Random') && !function_exists('crypt_random')) {
    require_once('Crypt/Random.php');
}

/**#@+
 * Encryption Methods
 *
 * @see Net_SSH1::getSupportedCiphers()
 * @access public
 */
/**
 * No encryption
 *
 * Not supported.
 */
define('NET_SSH1_CIPHER_NONE',       0);
/**
 * IDEA in CFB mode
 *
 * Not supported.
 */
define('NET_SSH1_CIPHER_IDEA',       1);
/**
 * DES in CBC mode
 */
define('NET_SSH1_CIPHER_DES',        2);
/**
 * Triple-DES in CBC mode
 *
 * All implementations are required to support this
 */
define('NET_SSH1_CIPHER_3DES',       3);
/**
 * TRI's Simple Stream encryption CBC
 *
 * Not supported nor is it defined in the official SSH1 specs.  OpenSSH, however, does define it (see cipher.h),
 * although it doesn't use it (see cipher.c)
 */
define('NET_SSH1_CIPHER_BROKEN_TSS', 4);
/**
 * RC4
 *
 * Not supported.
 *
 * @internal According to the SSH1 specs:
 *
 *        "The first 16 bytes of the session key are used as the key for
 *         the server to client direction.  The remaining 16 bytes are used
 *         as the key for the client to server direction.  This gives
 *         independent 128-bit keys for each direction."
 *
 *     This library currently only supports encryption when the same key is being used for both directions.  This is
 *     because there's only one $crypto object.  Two could be added ($encrypt and $decrypt, perhaps).
 */
define('NET_SSH1_CIPHER_RC4',        5);
/**
 * Blowfish
 *
 * Not supported nor is it defined in the official SSH1 specs.  OpenSSH, however, defines it (see cipher.h) and
 * uses it (see cipher.c)
 */
define('NET_SSH1_CIPHER_BLOWFISH',   6);
/**#@-*/

/**#@+
 * Authentication Methods
 *
 * @see Net_SSH1::getSupportedAuthentications()
 * @access public
 */
/**
 * .rhosts or /etc/hosts.equiv
 */
define('NET_SSH1_AUTH_RHOSTS',     1);
/**
 * pure RSA authentication
 */
define('NET_SSH1_AUTH_RSA',        2);
/**
 * password authentication
 *
 * This is the only method that is supported by this library.
 */
define('NET_SSH1_AUTH_PASSWORD',   3);
/**
 * .rhosts with RSA host authentication
 */
define('NET_SSH1_AUTH_RHOSTS_RSA', 4);
/**#@-*/

/**#@+
 * Terminal Modes
 *
 * @link http://3sp.com/content/developer/maverick-net/docs/Maverick.SSH.PseudoTerminalModesMembers.html
 * @access private
 */
define('NET_SSH1_TTY_OP_END',  0);
/**#@-*/

/**
 * The Response Type
 *
 * @see Net_SSH1::_get_binary_packet()
 * @access private
 */
define('NET_SSH1_RESPONSE_TYPE', 1);

/**
 * The Response Data
 *
 * @see Net_SSH1::_get_binary_packet()
 * @access private
 */
define('NET_SSH1_RESPONSE_DATA', 2);

/**#@+
 * Execution Bitmap Masks
 *
 * @see Net_SSH1::bitmap
 * @access private
 */
define('NET_SSH1_MASK_CONSTRUCTOR', 0x00000001);
define('NET_SSH1_MASK_LOGIN',       0x00000002);
define('NET_SSH1_MASK_SHELL',       0x00000004);
/**#@-*/

/**#@+
 * @access public
 * @see Net_SSH1::getLog()
 */
/**
 * Returns the message numbers
 */
define('NET_SSH1_LOG_SIMPLE',  1);
/**
 * Returns the message content
 */
define('NET_SSH1_LOG_COMPLEX', 2);
/**#@-*/

/**#@+
 * @access public
 * @see Net_SSH1::read()
 */
/**
 * Returns when a string matching $expect exactly is found
 */
define('NET_SSH1_READ_SIMPLE',  1);
/**
 * Returns when a string matching the regular expression $expect is found
 */
define('NET_SSH1_READ_REGEX', 2);
/**#@-*/

/**
 * Pure-PHP implementation of SSHv1.
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 * @version 0.1.0
 * @access  public
 * @package Net_SSH1
 */
class Net_SSH1 {
    /**
     * The SSH identifier
     *
     * @var String
     * @access private
     */
    var $identifier = 'SSH-1.5-phpseclib';

    /**
     * The Socket Object
     *
     * @var Object
     * @access private
     */
    var $fsock;

    /**
     * The cryptography object
     *
     * @var Object
     * @access private
     */
    var $crypto = false;

    /**
     * Execution Bitmap
     *
     * The bits that are set represent functions that have been called already.  This is used to determine
     * if a requisite function has been successfully executed.  If not, an error should be thrown.
     *
     * @var Integer
     * @access private
     */
    var $bitmap = 0;

    /**
     * The Server Key Public Exponent
     *
     * Logged for debug purposes
     *
     * @see Net_SSH1::getServerKeyPublicExponent()
     * @var String
     * @access private
     */
    var $server_key_public_exponent;

    /**
     * The Server Key Public Modulus
     *
     * Logged for debug purposes
     *
     * @see Net_SSH1::getServerKeyPublicModulus()
     * @var String
     * @access private
     */
    var $server_key_public_modulus;

    /**
     * The Host Key Public Exponent
     *
     * Logged for debug purposes
     *
     * @see Net_SSH1::getHostKeyPublicExponent()
     * @var String
     * @access private
     */
    var $host_key_public_exponent;

    /**
     * The Host Key Public Modulus
     *
     * Logged for debug purposes
     *
     * @see Net_SSH1::getHostKeyPublicModulus()
     * @var String
     * @access private
     */
    var $host_key_public_modulus;

    /**
     * Supported Ciphers
     *
     * Logged for debug purposes
     *
     * @see Net_SSH1::getSupportedCiphers()
     * @var Array
     * @access private
     */
    var $supported_ciphers = array(
        NET_SSH1_CIPHER_NONE       => 'No encryption',
        NET_SSH1_CIPHER_IDEA       => 'IDEA in CFB mode',
        NET_SSH1_CIPHER_DES        => 'DES in CBC mode',
        NET_SSH1_CIPHER_3DES       => 'Triple-DES in CBC mode',
        NET_SSH1_CIPHER_BROKEN_TSS => 'TRI\'s Simple Stream encryption CBC',
        NET_SSH1_CIPHER_RC4        => 'RC4',
        NET_SSH1_CIPHER_BLOWFISH   => 'Blowfish'
    );

    /**
     * Supported Authentications
     *
     * Logged for debug purposes
     *
     * @see Net_SSH1::getSupportedAuthentications()
     * @var Array
     * @access private
     */
    var $supported_authentications = array(
        NET_SSH1_AUTH_RHOSTS     => '.rhosts or /etc/hosts.equiv',
        NET_SSH1_AUTH_RSA        => 'pure RSA authentication',
        NET_SSH1_AUTH_PASSWORD   => 'password authentication',
        NET_SSH1_AUTH_RHOSTS_RSA => '.rhosts with RSA host authentication'
    );

    /**
     * Server Identification
     *
     * @see Net_SSH1::getServerIdentification()
     * @var String
     * @access private
     */
    var $server_identification = '';

    /**
     * Protocol Flags
     *
     * @see Net_SSH1::Net_SSH1()
     * @var Array
     * @access private
     */
    var $protocol_flags = array();

    /**
     * Protocol Flag Log
     *
     * @see Net_SSH1::getLog()
     * @var Array
     * @access private
     */
    var $protocol_flag_log = array();

    /**
     * Message Log
     *
     * @see Net_SSH1::getLog()
     * @var Array
     * @access private
     */
    var $message_log = array();

    /**
     * Interactive Buffer
     *
     * @see Net_SSH1::read()
     * @var Array
     * @access private
     */
    var $interactive_buffer = '';

    /**
     * Default Constructor.
     *
     * Connects to an SSHv1 server
     *
     * @param String $host
     * @param optional Integer $port
     * @param optional Integer $timeout
     * @param optional Integer $cipher
     * @return Net_SSH1
     * @access public
     */
    function Net_SSH1($host, $port = 22, $timeout = 10, $cipher = NET_SSH1_CIPHER_3DES)
    {
        $this->protocol_flags = array(
            1  => 'NET_SSH1_MSG_DISCONNECT',
            2  => 'NET_SSH1_SMSG_PUBLIC_KEY',
            3  => 'NET_SSH1_CMSG_SESSION_KEY',
            4  => 'NET_SSH1_CMSG_USER',
            9  => 'NET_SSH1_CMSG_AUTH_PASSWORD',
            10 => 'NET_SSH1_CMSG_REQUEST_PTY',
            12 => 'NET_SSH1_CMSG_EXEC_SHELL',
            13 => 'NET_SSH1_CMSG_EXEC_CMD',
            14 => 'NET_SSH1_SMSG_SUCCESS',
            15 => 'NET_SSH1_SMSG_FAILURE',
            16 => 'NET_SSH1_CMSG_STDIN_DATA',
            17 => 'NET_SSH1_SMSG_STDOUT_DATA',
            18 => 'NET_SSH1_SMSG_STDERR_DATA',
            19 => 'NET_SSH1_CMSG_EOF',
            20 => 'NET_SSH1_SMSG_EXITSTATUS',
            33 => 'NET_SSH1_CMSG_EXIT_CONFIRMATION'
        );

        $this->_define_array($this->protocol_flags);

        $this->fsock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->fsock) {
            user_error(rtrim("Cannot connect to $host. Error $errno. $errstr"), E_USER_NOTICE);
            return;
        }

        $this->server_identification = $init_line = fgets($this->fsock, 255);

        if (defined('NET_SSH1_LOGGING')) {
            $this->protocol_flags_log[] = '<-';
            $this->protocol_flags_log[] = '->';

            if (NET_SSH1_LOGGING == NET_SSH1_LOG_COMPLEX) {
                $this->message_log[] = $this->server_identification;
                $this->message_log[] = $this->identifier . "\r\n";
            }
        }

        if (!preg_match('#SSH-([0-9\.]+)-(.+)#', $init_line, $parts)) {
            user_error('Can only connect to SSH servers', E_USER_NOTICE);
            return;
        }
        if ($parts[1][0] != 1) {
            user_error("Cannot connect to SSH $parts[1] servers", E_USER_NOTICE);
            return;
        }

        fputs($this->fsock, $this->identifier."\r\n");

        $response = $this->_get_binary_packet();
        if ($response[NET_SSH1_RESPONSE_TYPE] != NET_SSH1_SMSG_PUBLIC_KEY) {
            user_error('Expected SSH_SMSG_PUBLIC_KEY', E_USER_NOTICE);
            return;
        }

        $anti_spoofing_cookie = $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 8);

        $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 4);

        $temp = unpack('nlen', $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 2));
        $server_key_public_exponent = new Math_BigInteger($this->_string_shift($response[NET_SSH1_RESPONSE_DATA], ceil($temp['len'] / 8)), 256);
        $this->server_key_public_exponent = $server_key_public_exponent;

        $temp = unpack('nlen', $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 2));
        $server_key_public_modulus = new Math_BigInteger($this->_string_shift($response[NET_SSH1_RESPONSE_DATA], ceil($temp['len'] / 8)), 256);
        $this->server_key_public_modulus = $server_key_public_modulus;

        $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 4);

        $temp = unpack('nlen', $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 2));
        $host_key_public_exponent = new Math_BigInteger($this->_string_shift($response[NET_SSH1_RESPONSE_DATA], ceil($temp['len'] / 8)), 256);
        $this->host_key_public_exponent = $host_key_public_exponent;

        $temp = unpack('nlen', $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 2));
        $host_key_public_modulus = new Math_BigInteger($this->_string_shift($response[NET_SSH1_RESPONSE_DATA], ceil($temp['len'] / 8)), 256);
        $this->host_key_public_modulus = $host_key_public_modulus;

        $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 4);

        // get a list of the supported ciphers
        extract(unpack('Nsupported_ciphers_mask', $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 4)));
        foreach ($this->supported_ciphers as $mask=>$name) {
            if (($supported_ciphers_mask & (1 << $mask)) == 0) {
                unset($this->supported_ciphers[$mask]);
            }
        }

        // get a list of the supported authentications
        extract(unpack('Nsupported_authentications_mask', $this->_string_shift($response[NET_SSH1_RESPONSE_DATA], 4)));
        foreach ($this->supported_authentications as $mask=>$name) {
            if (($supported_authentications_mask & (1 << $mask)) == 0) {
                unset($this->supported_authentications[$mask]);
            }
        }

        $session_id = pack('H*', md5($host_key_public_modulus->toBytes() . $server_key_public_modulus->toBytes() . $anti_spoofing_cookie));

        $session_key = '';
        for ($i = 0; $i < 32; $i++) {
            $session_key.= chr(crypt_random(0, 255));
        }
        $double_encrypted_session_key = $session_key ^ str_pad($session_id, 32, chr(0));

        if ($server_key_public_modulus->compare($host_key_public_modulus) < 0) {
            $double_encrypted_session_key = $this->_rsa_crypt(
                $double_encrypted_session_key,
                array(
                    $server_key_public_exponent,
                    $server_key_public_modulus
                )
            );
            $double_encrypted_session_key = $this->_rsa_crypt(
                $double_encrypted_session_key,
                array(
                    $host_key_public_exponent,
                    $host_key_public_modulus
                )
            );
        } else {
            $double_encrypted_session_key = $this->_rsa_crypt(
                $double_encrypted_session_key,
                array(
                    $host_key_public_exponent,
                    $host_key_public_modulus
                )
            );
            $double_encrypted_session_key = $this->_rsa_crypt(
                $double_encrypted_session_key,
                array(
                    $server_key_public_exponent,
                    $server_key_public_modulus
                )
            );
        }

        $cipher = isset($this->supported_ciphers[$cipher]) ? $cipher : NET_SSH1_CIPHER_3DES;
        $data = pack('C2a*na*N', NET_SSH1_CMSG_SESSION_KEY, $cipher, $anti_spoofing_cookie, 8 * strlen($double_encrypted_session_key), $double_encrypted_session_key, 0);

        if (!$this->_send_binary_packet($data)) {
            user_error('Error sending SSH_CMSG_SESSION_KEY', E_USER_NOTICE);
            return;
        }

        switch ($cipher) {
            //case NET_SSH1_CIPHER_NONE:
            //    $this->crypto = new Crypt_Null();
            //    break;
            case NET_SSH1_CIPHER_DES:
                $this->crypto = new Crypt_DES();
                $this->crypto->disablePadding();
                $this->crypto->enableContinuousBuffer();
                $this->crypto->setKey(substr($session_key, 0,  8));
                break;
            case NET_SSH1_CIPHER_3DES:
                $this->crypto = new Crypt_TripleDES(CRYPT_DES_MODE_3CBC);
                $this->crypto->disablePadding();
                $this->crypto->enableContinuousBuffer();
                $this->crypto->setKey(substr($session_key, 0, 24));
                break;
            //case NET_SSH1_CIPHER_RC4:
            //    $this->crypto = new Crypt_RC4();
            //    $this->crypto->enableContinuousBuffer();
            //    $this->crypto->setKey(substr($session_key, 0,  16));
            //    break;
        }

        $response = $this->_get_binary_packet();

        if ($response[NET_SSH1_RESPONSE_TYPE] != NET_SSH1_SMSG_SUCCESS) {
            user_error('Expected SSH_SMSG_SUCCESS', E_USER_NOTICE);
            return;
        }

        $this->bitmap = NET_SSH1_MASK_CONSTRUCTOR;
    }

    /**
     * Login
     *
     * @param String $username
     * @param optional String $password
     * @return Boolean
     * @access public
     */
    function login($username, $password = '')
    {
        if (!($this->bitmap & NET_SSH1_MASK_CONSTRUCTOR)) {
            return false;
        }

        $data = pack('CNa*', NET_SSH1_CMSG_USER, strlen($username), $username);

        if (!$this->_send_binary_packet($data)) {
            user_error('Error sending SSH_CMSG_USER', E_USER_NOTICE);
            return false;
        }

        $response = $this->_get_binary_packet();

        if ($response[NET_SSH1_RESPONSE_TYPE] == NET_SSH1_SMSG_SUCCESS) {
            $this->bitmap |= NET_SSH1_MASK_LOGIN;
            return true;
        } else if ($response[NET_SSH1_RESPONSE_TYPE] != NET_SSH1_SMSG_FAILURE) {
            user_error('Expected SSH_SMSG_SUCCESS or SSH_SMSG_FAILURE', E_USER_NOTICE);
            return false;
        }

        $data = pack('CNa*', NET_SSH1_CMSG_AUTH_PASSWORD, strlen($password), $password);

        if (!$this->_send_binary_packet($data)) {
            user_error('Error sending SSH_CMSG_AUTH_PASSWORD', E_USER_NOTICE);
            return false;
        }

        // remove the username and password from the last logged packet
        if (defined('NET_SSH1_LOGGING') && NET_SSH1_LOGGING == NET_SSH1_LOG_COMPLEX) {
            $data = pack('CNa*', NET_SSH1_CMSG_AUTH_PASSWORD, strlen('password'), 'password');
            $this->message_log[count($this->message_log) - 1] = $data; // zzzzz
        }

        $response = $this->_get_binary_packet();

        if ($response[NET_SSH1_RESPONSE_TYPE] == NET_SSH1_SMSG_SUCCESS) {
            $this->bitmap |= NET_SSH1_MASK_LOGIN;
            return true;
        } else if ($response[NET_SSH1_RESPONSE_TYPE] == NET_SSH1_SMSG_FAILURE) {
            return false;
        } else {
            user_error('Expected SSH_SMSG_SUCCESS or SSH_SMSG_FAILURE', E_USER_NOTICE);
            return false;
        }
    }

    /**
     * Executes a command on a non-interactive shell, returns the output, and quits.
     *
     * An SSH1 server will close the connection after a command has been executed on a non-interactive shell.  SSH2
     * servers don't, however, this isn't an SSH2 client.  The way this works, on the server, is by initiating a
     * shell with the -s option, as discussed in the following links:
     *
     * {@link http://www.faqs.org/docs/bashman/bashref_65.html http://www.faqs.org/docs/bashman/bashref_65.html}
     * {@link http://www.faqs.org/docs/bashman/bashref_62.html http://www.faqs.org/docs/bashman/bashref_62.html}
     *
     * To execute further commands, a new Net_SSH1 object will need to be created.
     *
     * Returns false on failure and the output, otherwise.
     *
     * @see Net_SSH1::interactiveRead()
     * @see Net_SSH1::interactiveWrite()
     * @param String $cmd
     * @return mixed
     * @access public
     */
    function exec($cmd, $block = true)
    {
        if (!($this->bitmap & NET_SSH1_MASK_LOGIN)) {
            user_error('Operation disallowed prior to login()', E_USER_NOTICE);
            return false;
        }

        $data = pack('CNa*', NET_SSH1_CMSG_EXEC_CMD, strlen($cmd), $cmd);

        if (!$this->_send_binary_packet($data)) {
            user_error('Error sending SSH_CMSG_EXEC_CMD', E_USER_NOTICE);
            return false;
        }

        if (!$block) {
            return true;
        }

        $output = '';
        $response = $this->_get_binary_packet();

        do {
            $output.= substr($response[NET_SSH1_RESPONSE_DATA], 4);
            $response = $this->_get_binary_packet();
        } while ($response[NET_SSH1_RESPONSE_TYPE] != NET_SSH1_SMSG_EXITSTATUS);

        $data = pack('C', NET_SSH1_CMSG_EXIT_CONFIRMATION);

        // i don't think it's really all that important if this packet gets sent or not.
        $this->_send_binary_packet($data);

        fclose($this->fsock);

        // reset the execution bitmap - a new Net_SSH1 object needs to be created.
        $this->bitmap = 0;

        return $output;
    }

    /**
     * Creates an interactive shell
     *
     * @see Net_SSH1::interactiveRead()
     * @see Net_SSH1::interactiveWrite()
     * @return Boolean
     * @access private
     */
    function _initShell()
    {
        // connect using the sample parameters in protocol-1.5.txt.
        // according to wikipedia.org's entry on text terminals, "the fundamental type of application running on a text
        // terminal is a command line interpreter or shell".  thus, opening a terminal session to run the shell.
        $data = pack('CNa*N4C', NET_SSH1_CMSG_REQUEST_PTY, strlen('vt100'), 'vt100', 24, 80, 0, 0, NET_SSH1_TTY_OP_END);

        if (!$this->_send_binary_packet($data)) {
            user_error('Error sending SSH_CMSG_REQUEST_PTY', E_USER_NOTICE);
            return false;
        }

        $response = $this->_get_binary_packet();

        if ($response[NET_SSH1_RESPONSE_TYPE] != NET_SSH1_SMSG_SUCCESS) {
            user_error('Expected SSH_SMSG_SUCCESS', E_USER_NOTICE);
            return false;
        }

        $data = pack('C', NET_SSH1_CMSG_EXEC_SHELL);

        if (!$this->_send_binary_packet($data)) {
            user_error('Error sending SSH_CMSG_EXEC_SHELL', E_USER_NOTICE);
            return false;
        }

        $this->bitmap |= NET_SSH1_MASK_SHELL;

        //stream_set_blocking($this->fsock, 0);

        return true;
    }

    /**
     * Inputs a command into an interactive shell.
     *
     * @see Net_SSH1::interactiveWrite()
     * @param String $cmd
     * @return Boolean
     * @access public
     */
    function write($cmd)
    {
        return $this->interactiveWrite($cmd);
    }

    /**
     * Returns the output of an interactive shell when there's a match for $expect
     *
     * $expect can take the form of a string literal or, if $mode == NET_SSH1_READ_REGEX,
     * a regular expression.
     *
     * @see Net_SSH1::write()
     * @param String $expect
     * @param Integer $mode
     * @return Boolean
     * @access public
     */
    function read($expect, $mode = NET_SSH1_READ_SIMPLE)
    {
        if (!($this->bitmap & NET_SSH1_MASK_LOGIN)) {
            user_error('Operation disallowed prior to login()', E_USER_NOTICE);
            return false;
        }

        if (!($this->bitmap & NET_SSH1_MASK_SHELL) && !$this->_initShell()) {
            user_error('Unable to initiate an interactive shell session', E_USER_NOTICE);
            return false;
        }

        $match = $expect;
        while (true) {
            if ($mode == NET_SSH1_READ_REGEX) {
                preg_match($expect, $this->interactiveBuffer, $matches);
                $match = $matches[0];
            }
            $pos = strpos($this->interactiveBuffer, $match);
            if ($pos !== false) {
                return $this->_string_shift($this->interactiveBuffer, $pos + strlen($match));
            }
            $response = $this->_get_binary_packet();
            $this->interactiveBuffer.= substr($response[NET_SSH1_RESPONSE_DATA], 4);
        }
    }

    /**
     * Inputs a command into an interactive shell.
     *
     * @see Net_SSH1::interactiveRead()
     * @param String $cmd
     * @return Boolean
     * @access public
     */
    function interactiveWrite($cmd)
    {
        if (!($this->bitmap & NET_SSH1_MASK_LOGIN)) {
            user_error('Operation disallowed prior to login()', E_USER_NOTICE);
            return false;
        }

        if (!($this->bitmap & NET_SSH1_MASK_SHELL) && !$this->_initShell()) {
            user_error('Unable to initiate an interactive shell session', E_USER_NOTICE);
            return false;
        }

        $data = pack('CNa*', NET_SSH1_CMSG_STDIN_DATA, strlen($cmd), $cmd);

        if (!$this->_send_binary_packet($data)) {
            user_error('Error sending SSH_CMSG_STDIN', E_USER_NOTICE);
            return false;
        }

        return true;
    }

    /**
     * Returns the output of an interactive shell when no more output is available.
     *
     * Requires PHP 4.3.0 or later due to the use of the stream_select() function.  If you see stuff like
     * "[00m", you're seeing ANSI escape codes.  According to
     * {@link http://support.microsoft.com/kb/101875 How to Enable ANSI.SYS in a Command Window}, "Windows NT
     * does not support ANSI escape sequences in Win32 Console applications", so if you're a Windows user,
     * there's not going to be much recourse.
     *
     * @see Net_SSH1::interactiveRead()
     * @return String
     * @access public
     */
    function interactiveRead()
    {
        if (!($this->bitmap & NET_SSH1_MASK_LOGIN)) {
            user_error('Operation disallowed prior to login()', E_USER_NOTICE);
            return false;
        }

        if (!($this->bitmap & NET_SSH1_MASK_SHELL) && !$this->_initShell()) {
            user_error('Unable to initiate an interactive shell session', E_USER_NOTICE);
            return false;
        }

        $read = array($this->fsock);
        $write = $except = null;
        if (stream_select($read, $write, $except, 0)) {
            $response = $this->_get_binary_packet();
            return substr($response[NET_SSH1_RESPONSE_DATA], 4);
        } else {
            return '';
        }
    }

    /**
     * Disconnect
     *
     * @access public
     */
    function disconnect()
    {
        $this->_disconnect();
    }

    /**
     * Destructor.
     *
     * Will be called, automatically, if you're supporting just PHP5.  If you're supporting PHP4, you'll need to call
     * disconnect().
     *
     * @access public
     */
    function __destruct()
    {
        $this->_disconnect();
    }

    /**
     * Disconnect
     *
     * @param String $msg
     * @access private
     */
    function _disconnect($msg = 'Client Quit')
    {
        if ($this->bitmap) {
            $data = pack('C', NET_SSH1_CMSG_EOF);
            $this->_send_binary_packet($data);

            $response = $this->_get_binary_packet();
            switch ($response[NET_SSH1_RESPONSE_TYPE]) {
                case NET_SSH1_SMSG_EXITSTATUS:
                    $data = pack('C', NET_SSH1_CMSG_EXIT_CONFIRMATION);
                    break;
                default:
                    $data = pack('CNa*', NET_SSH1_MSG_DISCONNECT, strlen($msg), $msg);
            }

            $this->_send_binary_packet($data);
            fclose($this->fsock);
            $this->bitmap = 0;
        }
    }

    /**
     * Gets Binary Packets
     *
     * See 'The Binary Packet Protocol' of protocol-1.5.txt for more info.
     *
     * Also, this function could be improved upon by adding detection for the following exploit:
     * http://www.securiteam.com/securitynews/5LP042K3FY.html
     *
     * @see Net_SSH1::_send_binary_packet()
     * @return Array
     * @access private
     */
    function _get_binary_packet()
    {
        if (feof($this->fsock)) {
            //user_error('connection closed prematurely', E_USER_NOTICE);
            return false;
        }

        $temp = unpack('Nlength', fread($this->fsock, 4));

        $padding_length = 8 - ($temp['length'] & 7);
        $length = $temp['length'] + $padding_length;

        $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838
        $raw = fread($this->fsock, $length);
        $stop = strtok(microtime(), ' ') + strtok('');

        if ($this->crypto !== false) {
            $raw = $this->crypto->decrypt($raw);
        }

        $padding = substr($raw, 0, $padding_length);
        $type = $raw[$padding_length];
        $data = substr($raw, $padding_length + 1, -4);

        $temp = unpack('Ncrc', substr($raw, -4));

        //if ( $temp['crc'] != $this->_crc($padding . $type . $data) ) {
        //    user_error('Bad CRC in packet from server', E_USER_NOTICE);
        //    return false;
        //}

        $type = ord($type);

        if (defined('NET_SSH1_LOGGING')) {
            $temp = isset($this->protocol_flags[$type]) ? $this->protocol_flags[$type] : 'UNKNOWN';
            $this->protocol_flags_log[] = '<- ' . $temp .
                                          ' (' . round($stop - $start, 4) . 's)';
            if (NET_SSH1_LOGGING == NET_SSH1_LOG_COMPLEX) {
                $this->message_log[] = $data;
            }
        }

        return array(
            NET_SSH1_RESPONSE_TYPE => $type,
            NET_SSH1_RESPONSE_DATA => $data
        );
    }

    /**
     * Sends Binary Packets
     *
     * Returns true on success, false on failure.
     *
     * @see Net_SSH1::_get_binary_packet()
     * @param String $data
     * @return Boolean
     * @access private
     */
    function _send_binary_packet($data) {
        if (feof($this->fsock)) {
            //user_error('connection closed prematurely', E_USER_NOTICE);
            return false;
        }

        if (defined('NET_SSH1_LOGGING')) {
            $temp = isset($this->protocol_flags[ord($data[0])]) ? $this->protocol_flags[ord($data[0])] : 'UNKNOWN';
            $this->protocol_flags_log[] = '-> ' . $temp .
                                          ' (' . round($stop - $start, 4) . 's)';
            if (NET_SSH1_LOGGING == NET_SSH1_LOG_COMPLEX) {
                $this->message_log[] = substr($data, 1);
            }
        }

        $length = strlen($data) + 4;

        $padding_length = 8 - ($length & 7);
        $padding = '';
        for ($i = 0; $i < $padding_length; $i++) {
            $padding.= chr(crypt_random(0, 255));
        }

        $data = $padding . $data;
        $data.= pack('N', $this->_crc($data));

        if ($this->crypto !== false) {
            $data = $this->crypto->encrypt($data);
        }

        $packet = pack('Na*', $length, $data);

        $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838
        $result = strlen($packet) == fputs($this->fsock, $packet);
        $stop = strtok(microtime(), ' ') + strtok('');

        return $result;
    }

    /**
     * Cyclic Redundancy Check (CRC)
     *
     * PHP's crc32 function is implemented slightly differently than the one that SSH v1 uses, so
     * we've reimplemented it. A more detailed discussion of the differences can be found after
     * $crc_lookup_table's initialization.
     *
     * @see Net_SSH1::_get_binary_packet()
     * @see Net_SSH1::_send_binary_packet()
     * @param String $data
     * @return Integer
     * @access private
     */
    function _crc($data)
    {
        static $crc_lookup_table = array(
            0x00000000, 0x77073096, 0xEE0E612C, 0x990951BA,
            0x076DC419, 0x706AF48F, 0xE963A535, 0x9E6495A3,
            0x0EDB8832, 0x79DCB8A4, 0xE0D5E91E, 0x97D2D988,
            0x09B64C2B, 0x7EB17CBD, 0xE7B82D07, 0x90BF1D91,
            0x1DB71064, 0x6AB020F2, 0xF3B97148, 0x84BE41DE,
            0x1ADAD47D, 0x6DDDE4EB, 0xF4D4B551, 0x83D385C7,
            0x136C9856, 0x646BA8C0, 0xFD62F97A, 0x8A65C9EC,
            0x14015C4F, 0x63066CD9, 0xFA0F3D63, 0x8D080DF5,
            0x3B6E20C8, 0x4C69105E, 0xD56041E4, 0xA2677172,
            0x3C03E4D1, 0x4B04D447, 0xD20D85FD, 0xA50AB56B,
            0x35B5A8FA, 0x42B2986C, 0xDBBBC9D6, 0xACBCF940,
            0x32D86CE3, 0x45DF5C75, 0xDCD60DCF, 0xABD13D59,
            0x26D930AC, 0x51DE003A, 0xC8D75180, 0xBFD06116,
            0x21B4F4B5, 0x56B3C423, 0xCFBA9599, 0xB8BDA50F,
            0x2802B89E, 0x5F058808, 0xC60CD9B2, 0xB10BE924,
            0x2F6F7C87, 0x58684C11, 0xC1611DAB, 0xB6662D3D,
            0x76DC4190, 0x01DB7106, 0x98D220BC, 0xEFD5102A,
            0x71B18589, 0x06B6B51F, 0x9FBFE4A5, 0xE8B8D433,
            0x7807C9A2, 0x0F00F934, 0x9609A88E, 0xE10E9818,
            0x7F6A0DBB, 0x086D3D2D, 0x91646C97, 0xE6635C01,
            0x6B6B51F4, 0x1C6C6162, 0x856530D8, 0xF262004E,
            0x6C0695ED, 0x1B01A57B, 0x8208F4C1, 0xF50FC457,
            0x65B0D9C6, 0x12B7E950, 0x8BBEB8EA, 0xFCB9887C,
            0x62DD1DDF, 0x15DA2D49, 0x8CD37CF3, 0xFBD44C65,
            0x4DB26158, 0x3AB551CE, 0xA3BC0074, 0xD4BB30E2,
            0x4ADFA541, 0x3DD895D7, 0xA4D1C46D, 0xD3D6F4FB,
            0x4369E96A, 0x346ED9FC, 0xAD678846, 0xDA60B8D0,
            0x44042D73, 0x33031DE5, 0xAA0A4C5F, 0xDD0D7CC9,
            0x5005713C, 0x270241AA, 0xBE0B1010, 0xC90C2086,
            0x5768B525, 0x206F85B3, 0xB966D409, 0xCE61E49F,
            0x5EDEF90E, 0x29D9C998, 0xB0D09822, 0xC7D7A8B4,
            0x59B33D17, 0x2EB40D81, 0xB7BD5C3B, 0xC0BA6CAD,
            0xEDB88320, 0x9ABFB3B6, 0x03B6E20C, 0x74B1D29A,
            0xEAD54739, 0x9DD277AF, 0x04DB2615, 0x73DC1683,
            0xE3630B12, 0x94643B84, 0x0D6D6A3E, 0x7A6A5AA8,
            0xE40ECF0B, 0x9309FF9D, 0x0A00AE27, 0x7D079EB1,
            0xF00F9344, 0x8708A3D2, 0x1E01F268, 0x6906C2FE,
            0xF762575D, 0x806567CB, 0x196C3671, 0x6E6B06E7,
            0xFED41B76, 0x89D32BE0, 0x10DA7A5A, 0x67DD4ACC,
            0xF9B9DF6F, 0x8EBEEFF9, 0x17B7BE43, 0x60B08ED5,
            0xD6D6A3E8, 0xA1D1937E, 0x38D8C2C4, 0x4FDFF252,
            0xD1BB67F1, 0xA6BC5767, 0x3FB506DD, 0x48B2364B,
            0xD80D2BDA, 0xAF0A1B4C, 0x36034AF6, 0x41047A60,
            0xDF60EFC3, 0xA867DF55, 0x316E8EEF, 0x4669BE79,
            0xCB61B38C, 0xBC66831A, 0x256FD2A0, 0x5268E236,
            0xCC0C7795, 0xBB0B4703, 0x220216B9, 0x5505262F,
            0xC5BA3BBE, 0xB2BD0B28, 0x2BB45A92, 0x5CB36A04,
            0xC2D7FFA7, 0xB5D0CF31, 0x2CD99E8B, 0x5BDEAE1D,
            0x9B64C2B0, 0xEC63F226, 0x756AA39C, 0x026D930A,
            0x9C0906A9, 0xEB0E363F, 0x72076785, 0x05005713,
            0x95BF4A82, 0xE2B87A14, 0x7BB12BAE, 0x0CB61B38,
            0x92D28E9B, 0xE5D5BE0D, 0x7CDCEFB7, 0x0BDBDF21,
            0x86D3D2D4, 0xF1D4E242, 0x68DDB3F8, 0x1FDA836E,
            0x81BE16CD, 0xF6B9265B, 0x6FB077E1, 0x18B74777,
            0x88085AE6, 0xFF0F6A70, 0x66063BCA, 0x11010B5C,
            0x8F659EFF, 0xF862AE69, 0x616BFFD3, 0x166CCF45,
            0xA00AE278, 0xD70DD2EE, 0x4E048354, 0x3903B3C2,
            0xA7672661, 0xD06016F7, 0x4969474D, 0x3E6E77DB,
            0xAED16A4A, 0xD9D65ADC, 0x40DF0B66, 0x37D83BF0,
            0xA9BCAE53, 0xDEBB9EC5, 0x47B2CF7F, 0x30B5FFE9,
            0xBDBDF21C, 0xCABAC28A, 0x53B39330, 0x24B4A3A6,
            0xBAD03605, 0xCDD70693, 0x54DE5729, 0x23D967BF,
            0xB3667A2E, 0xC4614AB8, 0x5D681B02, 0x2A6F2B94,
            0xB40BBE37, 0xC30C8EA1, 0x5A05DF1B, 0x2D02EF8D
        );

        // For this function to yield the same output as PHP's crc32 function, $crc would have to be
        // set to 0xFFFFFFFF, initially - not 0x00000000 as it currently is.
        $crc = 0x00000000;
        $length = strlen($data);

        for ($i=0;$i<$length;$i++) {
            // We AND $crc >> 8 with 0x00FFFFFF because we want the eight newly added bits to all
            // be zero.  PHP, unfortunately, doesn't always do this.  0x80000000 >> 8, as an example,
            // yields 0xFF800000 - not 0x00800000.  The following link elaborates:
            // http://www.php.net/manual/en/language.operators.bitwise.php#57281
            $crc = (($crc >> 8) & 0x00FFFFFF) ^ $crc_lookup_table[($crc & 0xFF) ^ ord($data[$i])];
        }

        // In addition to having to set $crc to 0xFFFFFFFF, initially, the return value must be XOR'd with
        // 0xFFFFFFFF for this function to return the same thing that PHP's crc32 function would.
        return $crc;
    }

    /**
     * String Shift
     *
     * Inspired by array_shift
     *
     * @param String $string
     * @param optional Integer $index
     * @return String
     * @access private
     */
    function _string_shift(&$string, $index = 1)
    {
        $substr = substr($string, 0, $index);
        $string = substr($string, $index);
        return $substr;
    }

    /**
     * RSA Encrypt
     *
     * Returns mod(pow($m, $e), $n), where $n should be the product of two (large) primes $p and $q and where $e
     * should be a number with the property that gcd($e, ($p - 1) * ($q - 1)) == 1.  Could just make anything that
     * calls this call modexp, instead, but I think this makes things clearer, maybe...
     *
     * @see Net_SSH1::Net_SSH1()
     * @param Math_BigInteger $m
     * @param Array $key
     * @return Math_BigInteger
     * @access private
     */
    function _rsa_crypt($m, $key)
    {
        /*
        if (!class_exists('Crypt_RSA')) {
            require_once('Crypt/RSA.php');
        }

        $rsa = new Crypt_RSA();
        $rsa->loadKey($key, CRYPT_RSA_PUBLIC_FORMAT_RAW);
        $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
        return $rsa->encrypt($m);
        */

        // To quote from protocol-1.5.txt:
        // The most significant byte (which is only partial as the value must be
        // less than the public modulus, which is never a power of two) is zero.
        //
        // The next byte contains the value 2 (which stands for public-key
        // encrypted data in the PKCS standard [PKCS#1]).  Then, there are non-
        // zero random bytes to fill any unused space, a zero byte, and the data
        // to be encrypted in the least significant bytes, the last byte of the
        // data in the least significant byte.

        // Presumably the part of PKCS#1 they're refering to is "Section 7.2.1 Encryption Operation",
        // under "7.2 RSAES-PKCS1-v1.5" and "7 Encryption schemes" of the following URL:
        // ftp://ftp.rsasecurity.com/pub/pkcs/pkcs-1/pkcs-1v2-1.pdf
        $temp = chr(0) . chr(2);
        $modulus = $key[1]->toBytes();
        $length = strlen($modulus) - strlen($m) - 3;
        for ($i = 0; $i < $length; $i++) {
            $temp.= chr(crypt_random(1, 255));
        }
        $temp.= chr(0) . $m;

        $m = new Math_BigInteger($temp, 256);
        $m = $m->modPow($key[0], $key[1]);

        return $m->toBytes();
    }

    /**
     * Define Array
     *
     * Takes any number of arrays whose indices are integers and whose values are strings and defines a bunch of
     * named constants from it, using the value as the name of the constant and the index as the value of the constant.
     * If any of the constants that would be defined already exists, none of the constants will be defined.
     *
     * @param Array $array
     * @access private
     */
    function _define_array()
    {
        $args = func_get_args();
        foreach ($args as $arg) {
            foreach ($arg as $key=>$value) {
                if (!defined($value)) {
                    define($value, $key);
                } else {
                    break 2;
                }
            }
        }
    }

    /**
     * Returns a log of the packets that have been sent and received.
     *
     * Returns a string if NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX, an array if NET_SSH2_LOGGING == NET_SSH2_LOG_SIMPLE and false if !defined('NET_SSH2_LOGGING')
     *
     * @access public
     * @return String or Array
     */
    function getLog()
    {
        if (!defined('NET_SSH1_LOGGING')) {
            return false;
        }

        switch (NET_SSH1_LOGGING) {
            case NET_SSH1_LOG_SIMPLE:
                return $this->message_number_log;
                break;
            case NET_SSH1_LOG_COMPLEX:
                return $this->_format_log($this->message_log, $this->protocol_flags_log);
                break;
            default:
                return false;
        }
    }

    /**
     * Formats a log for printing
     *
     * @param Array $message_log
     * @param Array $message_number_log
     * @access private
     * @return String
     */
    function _format_log($message_log, $message_number_log)
    {
        static $boundary = ':', $long_width = 65, $short_width = 16;

        $output = '';
        for ($i = 0; $i < count($message_log); $i++) {
            $output.= $message_number_log[$i] . "\r\n";
            $current_log = $message_log[$i];
            $j = 0;
            do {
                if (!empty($current_log)) {
                    $output.= str_pad(dechex($j), 7, '0', STR_PAD_LEFT) . '0  ';
                }
                $fragment = $this->_string_shift($current_log, $short_width);
                $hex = substr(
                           preg_replace(
                               '#(.)#es',
                               '"' . $boundary . '" . str_pad(dechex(ord(substr("\\1", -1))), 2, "0", STR_PAD_LEFT)',
                               $fragment),
                           strlen($boundary)
                       );
                // replace non ASCII printable characters with dots
                // http://en.wikipedia.org/wiki/ASCII#ASCII_printable_characters
                // also replace < with a . since < messes up the output on web browsers
                $raw = preg_replace('#[^\x20-\x7E]|<#', '.', $fragment);
                $output.= str_pad($hex, $long_width - $short_width, ' ') . $raw . "\r\n";
                $j++;
            } while (!empty($current_log));
            $output.= "\r\n";
        }

        return $output;
    }

    /**
     * Return the server key public exponent
     *
     * Returns, by default, the base-10 representation.  If $raw_output is set to true, returns, instead,
     * the raw bytes.  This behavior is similar to PHP's md5() function.
     *
     * @param optional Boolean $raw_output
     * @return String
     * @access public
     */
    function getServerKeyPublicExponent($raw_output = false)
    {
        return $raw_output ? $this->server_key_public_exponent->toBytes() : $this->server_key_public_exponent->toString();
    }

    /**
     * Return the server key public modulus
     *
     * Returns, by default, the base-10 representation.  If $raw_output is set to true, returns, instead,
     * the raw bytes.  This behavior is similar to PHP's md5() function.
     *
     * @param optional Boolean $raw_output
     * @return String
     * @access public
     */
    function getServerKeyPublicModulus($raw_output = false)
    {
        return $raw_output ? $this->server_key_public_modulus->toBytes() : $this->server_key_public_modulus->toString();
    }

    /**
     * Return the host key public exponent
     *
     * Returns, by default, the base-10 representation.  If $raw_output is set to true, returns, instead,
     * the raw bytes.  This behavior is similar to PHP's md5() function.
     *
     * @param optional Boolean $raw_output
     * @return String
     * @access public
     */
    function getHostKeyPublicExponent($raw_output = false)
    {
        return $raw_output ? $this->host_key_public_exponent->toBytes() : $this->host_key_public_exponent->toString();
    }

    /**
     * Return the host key public modulus
     *
     * Returns, by default, the base-10 representation.  If $raw_output is set to true, returns, instead,
     * the raw bytes.  This behavior is similar to PHP's md5() function.
     *
     * @param optional Boolean $raw_output
     * @return String
     * @access public
     */
    function getHostKeyPublicModulus($raw_output = false)
    {
        return $raw_output ? $this->host_key_public_modulus->toBytes() : $this->host_key_public_modulus->toString();
    }

    /**
     * Return a list of ciphers supported by SSH1 server.
     *
     * Just because a cipher is supported by an SSH1 server doesn't mean it's supported by this library. If $raw_output
     * is set to true, returns, instead, an array of constants.  ie. instead of array('Triple-DES in CBC mode'), you'll
     * get array(NET_SSH1_CIPHER_3DES).
     *
     * @param optional Boolean $raw_output
     * @return Array
     * @access public
     */
    function getSupportedCiphers($raw_output = false)
    {
        return $raw_output ? array_keys($this->supported_ciphers) : array_values($this->supported_ciphers);
    }

    /**
     * Return a list of authentications supported by SSH1 server.
     *
     * Just because a cipher is supported by an SSH1 server doesn't mean it's supported by this library. If $raw_output
     * is set to true, returns, instead, an array of constants.  ie. instead of array('password authentication'), you'll
     * get array(NET_SSH1_AUTH_PASSWORD).
     *
     * @param optional Boolean $raw_output
     * @return Array
     * @access public
     */
    function getSupportedAuthentications($raw_output = false)
    {
        return $raw_output ? array_keys($this->supported_authentications) : array_values($this->supported_authentications);
    }

    /**
     * Return the server identification.
     *
     * @return String
     * @access public
     */
    function getServerIdentification()
    {
        return rtrim($this->server_identification);
    }
}

<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Pure-PHP implementation of SSHv2.
 *
 * PHP versions 4 and 5
 *
 * Here are some examples of how to use this library:
 * <code>
 * <?php
 *    include('Net/SSH2.php');
 *
 *    $ssh = new Net_SSH2('www.domain.tld');
 *    if (!$ssh->login('username', 'password')) {
 *        exit('Login Failed');
 *    }
 *
 *    echo $ssh->exec('pwd');
 *    echo $ssh->exec('ls -la');
 * ?>
 * </code>
 *
 * <code>
 * <?php
 *    include('Crypt/RSA.php');
 *    include('Net/SSH2.php');
 *
 *    $key = new Crypt_RSA();
 *    //$key->setPassword('whatever');
 *    $key->loadKey(file_get_contents('privatekey'));
 *
 *    $ssh = new Net_SSH2('www.domain.tld');
 *    if (!$ssh->login('username', $key)) {
 *        exit('Login Failed');
 *    }
 *
 *    echo $ssh->read('username@username:~$');
 *    $ssh->write("ls -la\n");
 *    echo $ssh->read('username@username:~$');
 * ?>
 * </code>
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @category   Net
 * @package    Net_SSH2
 * @author     Jim Wigginton <terrafrost@php.net>
 * @copyright  MMVII Jim Wigginton
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 * @version    $Id: SSH2.php,v 1.53 2010-10-24 01:24:30 terrafrost Exp $
 * @link       http://phpseclib.sourceforge.net
 */

/**
 * Include Math_BigInteger
 *
 * Used to do Diffie-Hellman key exchange and DSA/RSA signature verification.
 */
if (!class_exists('Math_BigInteger')) {
    require_once('Math/BigInteger.php');
}

/**
 * Include Crypt_Random
 */
// the class_exists() will only be called if the crypt_random function hasn't been defined and
// will trigger a call to __autoload() if you're wanting to auto-load classes
// call function_exists() a second time to stop the require_once from being called outside
// of the auto loader
if (!function_exists('crypt_random') && !class_exists('Crypt_Random') && !function_exists('crypt_random')) {
    require_once('Crypt/Random.php');
}

/**
 * Include Crypt_Hash
 */
if (!class_exists('Crypt_Hash')) {
    require_once('Crypt/Hash.php');
}

/**
 * Include Crypt_TripleDES
 */
if (!class_exists('Crypt_TripleDES')) {
    require_once('Crypt/TripleDES.php');
}

/**
 * Include Crypt_RC4
 */
if (!class_exists('Crypt_RC4')) {
    require_once('Crypt/RC4.php');
}

/**
 * Include Crypt_AES
 */
if (!class_exists('Crypt_AES')) {
    require_once('Crypt/AES.php');
}

/**#@+
 * Execution Bitmap Masks
 *
 * @see Net_SSH2::bitmap
 * @access private
 */
define('NET_SSH2_MASK_CONSTRUCTOR', 0x00000001);
define('NET_SSH2_MASK_LOGIN',       0x00000002);
define('NET_SSH2_MASK_SHELL',       0x00000004);
/**#@-*/

/**#@+
 * Channel constants
 *
 * RFC4254 refers not to client and server channels but rather to sender and recipient channels.  we don't refer
 * to them in that way because RFC4254 toggles the meaning. the client sends a SSH_MSG_CHANNEL_OPEN message with
 * a sender channel and the server sends a SSH_MSG_CHANNEL_OPEN_CONFIRMATION in response, with a sender and a
 * recepient channel.  at first glance, you might conclude that SSH_MSG_CHANNEL_OPEN_CONFIRMATION's sender channel
 * would be the same thing as SSH_MSG_CHANNEL_OPEN's sender channel, but it's not, per this snipet:
 *     The 'recipient channel' is the channel number given in the original
 *     open request, and 'sender channel' is the channel number allocated by
 *     the other side.
 *
 * @see Net_SSH2::_send_channel_packet()
 * @see Net_SSH2::_get_channel_packet()
 * @access private
 */
define('NET_SSH2_CHANNEL_EXEC', 0); // PuTTy uses 0x100
define('NET_SSH2_CHANNEL_SHELL',1);
/**#@-*/

/**#@+
 * @access public
 * @see Net_SSH2::getLog()
 */
/**
 * Returns the message numbers
 */
define('NET_SSH2_LOG_SIMPLE',  1);
/**
 * Returns the message content
 */
define('NET_SSH2_LOG_COMPLEX', 2);
/**
 * Outputs the content real-time
 */
define('NET_SSH2_LOG_REALTIME', 3);
/**#@-*/

/**#@+
 * @access public
 * @see Net_SSH2::read()
 */
/**
 * Returns when a string matching $expect exactly is found
 */
define('NET_SSH2_READ_SIMPLE',  1);
/**
 * Returns when a string matching the regular expression $expect is found
 */
define('NET_SSH2_READ_REGEX', 2);
/**
 * Make sure that the log never gets larger than this
 */
define('NET_SSH2_LOG_MAX_SIZE', 1024 * 1024);
/**#@-*/

/**
 * Pure-PHP implementation of SSHv2.
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 * @version 0.1.0
 * @access  public
 * @package Net_SSH2
 */
class Net_SSH2 {
    /**
     * The SSH identifier
     *
     * @var String
     * @access private
     */
    var $identifier = 'SSH-2.0-phpseclib_0.3';

    /**
     * The Socket Object
     *
     * @var Object
     * @access private
     */
    var $fsock;

    /**
     * Execution Bitmap
     *
     * The bits that are set reprsent functions that have been called already.  This is used to determine
     * if a requisite function has been successfully executed.  If not, an error should be thrown.
     *
     * @var Integer
     * @access private
     */
    var $bitmap = 0;

    /**
     * Error information
     *
     * @see Net_SSH2::getErrors()
     * @see Net_SSH2::getLastError()
     * @var String
     * @access private
     */
    var $errors = array();

    /**
     * Server Identifier
     *
     * @see Net_SSH2::getServerIdentification()
     * @var String
     * @access private
     */
    var $server_identifier = '';

    /**
     * Key Exchange Algorithms
     *
     * @see Net_SSH2::getKexAlgorithims()
     * @var Array
     * @access private
     */
    var $kex_algorithms;

    /**
     * Server Host Key Algorithms
     *
     * @see Net_SSH2::getServerHostKeyAlgorithms()
     * @var Array
     * @access private
     */
    var $server_host_key_algorithms;

    /**
     * Encryption Algorithms: Client to Server
     *
     * @see Net_SSH2::getEncryptionAlgorithmsClient2Server()
     * @var Array
     * @access private
     */
    var $encryption_algorithms_client_to_server;

    /**
     * Encryption Algorithms: Server to Client
     *
     * @see Net_SSH2::getEncryptionAlgorithmsServer2Client()
     * @var Array
     * @access private
     */
    var $encryption_algorithms_server_to_client;

    /**
     * MAC Algorithms: Client to Server
     *
     * @see Net_SSH2::getMACAlgorithmsClient2Server()
     * @var Array
     * @access private
     */
    var $mac_algorithms_client_to_server;

    /**
     * MAC Algorithms: Server to Client
     *
     * @see Net_SSH2::getMACAlgorithmsServer2Client()
     * @var Array
     * @access private
     */
    var $mac_algorithms_server_to_client;

    /**
     * Compression Algorithms: Client to Server
     *
     * @see Net_SSH2::getCompressionAlgorithmsClient2Server()
     * @var Array
     * @access private
     */
    var $compression_algorithms_client_to_server;

    /**
     * Compression Algorithms: Server to Client
     *
     * @see Net_SSH2::getCompressionAlgorithmsServer2Client()
     * @var Array
     * @access private
     */
    var $compression_algorithms_server_to_client;

    /**
     * Languages: Server to Client
     *
     * @see Net_SSH2::getLanguagesServer2Client()
     * @var Array
     * @access private
     */
    var $languages_server_to_client;

    /**
     * Languages: Client to Server
     *
     * @see Net_SSH2::getLanguagesClient2Server()
     * @var Array
     * @access private
     */
    var $languages_client_to_server;

    /**
     * Block Size for Server to Client Encryption
     *
     * "Note that the length of the concatenation of 'packet_length',
     *  'padding_length', 'payload', and 'random padding' MUST be a multiple
     *  of the cipher block size or 8, whichever is larger.  This constraint
     *  MUST be enforced, even when using stream ciphers."
     *
     *  -- http://tools.ietf.org/html/rfc4253#section-6
     *
     * @see Net_SSH2::Net_SSH2()
     * @see Net_SSH2::_send_binary_packet()
     * @var Integer
     * @access private
     */
    var $encrypt_block_size = 8;

    /**
     * Block Size for Client to Server Encryption
     *
     * @see Net_SSH2::Net_SSH2()
     * @see Net_SSH2::_get_binary_packet()
     * @var Integer
     * @access private
     */
    var $decrypt_block_size = 8;

    /**
     * Server to Client Encryption Object
     *
     * @see Net_SSH2::_get_binary_packet()
     * @var Object
     * @access private
     */
    var $decrypt = false;

    /**
     * Client to Server Encryption Object
     *
     * @see Net_SSH2::_send_binary_packet()
     * @var Object
     * @access private
     */
    var $encrypt = false;

    /**
     * Client to Server HMAC Object
     *
     * @see Net_SSH2::_send_binary_packet()
     * @var Object
     * @access private
     */
    var $hmac_create = false;

    /**
     * Server to Client HMAC Object
     *
     * @see Net_SSH2::_get_binary_packet()
     * @var Object
     * @access private
     */
    var $hmac_check = false;

    /**
     * Size of server to client HMAC
     *
     * We need to know how big the HMAC will be for the server to client direction so that we know how many bytes to read.
     * For the client to server side, the HMAC object will make the HMAC as long as it needs to be.  All we need to do is
     * append it.
     *
     * @see Net_SSH2::_get_binary_packet()
     * @var Integer
     * @access private
     */
    var $hmac_size = false;

    /**
     * Server Public Host Key
     *
     * @see Net_SSH2::getServerPublicHostKey()
     * @var String
     * @access private
     */
    var $server_public_host_key;

    /**
     * Session identifer
     *
     * "The exchange hash H from the first key exchange is additionally
     *  used as the session identifier, which is a unique identifier for
     *  this connection."
     *
     *  -- http://tools.ietf.org/html/rfc4253#section-7.2
     *
     * @see Net_SSH2::_key_exchange()
     * @var String
     * @access private
     */
    var $session_id = false;

    /**
     * Exchange hash
     *
     * The current exchange hash
     *
     * @see Net_SSH2::_key_exchange()
     * @var String
     * @access private
     */
    var $exchange_hash = false;

    /**
     * Message Numbers
     *
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $message_numbers = array();

    /**
     * Disconnection Message 'reason codes' defined in RFC4253
     *
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $disconnect_reasons = array();

    /**
     * SSH_MSG_CHANNEL_OPEN_FAILURE 'reason codes', defined in RFC4254
     *
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $channel_open_failure_reasons = array();

    /**
     * Terminal Modes
     *
     * @link http://tools.ietf.org/html/rfc4254#section-8
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $terminal_modes = array();

    /**
     * SSH_MSG_CHANNEL_EXTENDED_DATA's data_type_codes
     *
     * @link http://tools.ietf.org/html/rfc4254#section-5.2
     * @see Net_SSH2::Net_SSH2()
     * @var Array
     * @access private
     */
    var $channel_extended_data_type_codes = array();

    /**
     * Send Sequence Number
     *
     * See 'Section 6.4.  Data Integrity' of rfc4253 for more info.
     *
     * @see Net_SSH2::_send_binary_packet()
     * @var Integer
     * @access private
     */
    var $send_seq_no = 0;

    /**
     * Get Sequence Number
     *
     * See 'Section 6.4.  Data Integrity' of rfc4253 for more info.
     *
     * @see Net_SSH2::_get_binary_packet()
     * @var Integer
     * @access private
     */
    var $get_seq_no = 0;

    /**
     * Server Channels
     *
     * Maps client channels to server channels
     *
     * @see Net_SSH2::_get_channel_packet()
     * @see Net_SSH2::exec()
     * @var Array
     * @access private
     */
    var $server_channels = array();

    /**
     * Channel Buffers
     *
     * If a client requests a packet from one channel but receives two packets from another those packets should
     * be placed in a buffer
     *
     * @see Net_SSH2::_get_channel_packet()
     * @see Net_SSH2::exec()
     * @var Array
     * @access private
     */
    var $channel_buffers = array();

    /**
     * Channel Status
     *
     * Contains the type of the last sent message
     *
     * @see Net_SSH2::_get_channel_packet()
     * @var Array
     * @access private
     */
    var $channel_status = array();

    /**
     * Packet Size
     *
     * Maximum packet size indexed by channel
     *
     * @see Net_SSH2::_send_channel_packet()
     * @var Array
     * @access private
     */
    var $packet_size_client_to_server = array();

    /**
     * Message Number Log
     *
     * @see Net_SSH2::getLog()
     * @var Array
     * @access private
     */
    var $message_number_log = array();

    /**
     * Message Log
     *
     * @see Net_SSH2::getLog()
     * @var Array
     * @access private
     */
    var $message_log = array();

    /**
     * The Window Size
     *
     * Bytes the other party can send before it must wait for the window to be adjusted (0x7FFFFFFF = 4GB)
     *
     * @var Integer
     * @see Net_SSH2::_send_channel_packet()
     * @see Net_SSH2::exec()
     * @access private
     */
    var $window_size = 0x7FFFFFFF;

    /**
     * Window size
     *
     * Window size indexed by channel
     *
     * @see Net_SSH2::_send_channel_packet()
     * @var Array
     * @access private
     */
    var $window_size_client_to_server = array();

    /**
     * Server signature
     *
     * Verified against $this->session_id
     *
     * @see Net_SSH2::getServerPublicHostKey()
     * @var String
     * @access private
     */
    var $signature = '';

    /**
     * Server signature format
     *
     * ssh-rsa or ssh-dss.
     *
     * @see Net_SSH2::getServerPublicHostKey()
     * @var String
     * @access private
     */
    var $signature_format = '';

    /**
     * Interactive Buffer
     *
     * @see Net_SSH2::read()
     * @var Array
     * @access private
     */
    var $interactiveBuffer = '';

    /**
     * Current log size
     *
     * Should never exceed NET_SSH2_LOG_MAX_SIZE
     *
     * @see Net_SSH2::_send_binary_packet()
     * @see Net_SSH2::_get_binary_packet()
     * @var Integer
     * @access private
     */
    var $log_size;

    /**
     * Timeout
     *
     * @see Net_SSH2::setTimeout()
     * @access private
     */
    var $timeout;

    /**
     * Current Timeout
     *
     * @see Net_SSH2::_get_channel_packet()
     * @access private
     */
    var $curTimeout;

    /**
     * Real-time log file pointer
     *
     * @see Net_SSH2::_append_log()
     * @access private
     */
    var $realtime_log_file;

    /**
     * Real-time log file size
     *
     * @see Net_SSH2::_append_log()
     * @access private
     */
    var $realtime_log_size;

    /**
     * Has the signature been validated?
     *
     * @see Net_SSH2::getServerPublicHostKey()
     * @access private
     */
    var $signature_validated = false;

    /**
     * Real-time log file wrap boolean
     *
     * @see Net_SSH2::_append_log()
     * @access private
     */
    var $realtime_log_wrap;

    /**
     * Default Constructor.
     *
     * Connects to an SSHv2 server
     *
     * @param String $host
     * @param optional Integer $port
     * @param optional Integer $timeout
     * @return Net_SSH2
     * @access public
     */
    function Net_SSH2($host, $port = 22, $timeout = 10)
    {
        $this->message_numbers = array(
            1 => 'NET_SSH2_MSG_DISCONNECT',
            2 => 'NET_SSH2_MSG_IGNORE',
            3 => 'NET_SSH2_MSG_UNIMPLEMENTED',
            4 => 'NET_SSH2_MSG_DEBUG',
            5 => 'NET_SSH2_MSG_SERVICE_REQUEST',
            6 => 'NET_SSH2_MSG_SERVICE_ACCEPT',
            20 => 'NET_SSH2_MSG_KEXINIT',
            21 => 'NET_SSH2_MSG_NEWKEYS',
            30 => 'NET_SSH2_MSG_KEXDH_INIT',
            31 => 'NET_SSH2_MSG_KEXDH_REPLY',
            50 => 'NET_SSH2_MSG_USERAUTH_REQUEST',
            51 => 'NET_SSH2_MSG_USERAUTH_FAILURE',
            52 => 'NET_SSH2_MSG_USERAUTH_SUCCESS',
            53 => 'NET_SSH2_MSG_USERAUTH_BANNER',

            80 => 'NET_SSH2_MSG_GLOBAL_REQUEST',
            81 => 'NET_SSH2_MSG_REQUEST_SUCCESS',
            82 => 'NET_SSH2_MSG_REQUEST_FAILURE',
            90 => 'NET_SSH2_MSG_CHANNEL_OPEN',
            91 => 'NET_SSH2_MSG_CHANNEL_OPEN_CONFIRMATION',
            92 => 'NET_SSH2_MSG_CHANNEL_OPEN_FAILURE',
            93 => 'NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST',
            94 => 'NET_SSH2_MSG_CHANNEL_DATA',
            95 => 'NET_SSH2_MSG_CHANNEL_EXTENDED_DATA',
            96 => 'NET_SSH2_MSG_CHANNEL_EOF',
            97 => 'NET_SSH2_MSG_CHANNEL_CLOSE',
            98 => 'NET_SSH2_MSG_CHANNEL_REQUEST',
            99 => 'NET_SSH2_MSG_CHANNEL_SUCCESS',
            100 => 'NET_SSH2_MSG_CHANNEL_FAILURE'
        );
        $this->disconnect_reasons = array(
            1 => 'NET_SSH2_DISCONNECT_HOST_NOT_ALLOWED_TO_CONNECT',
            2 => 'NET_SSH2_DISCONNECT_PROTOCOL_ERROR',
            3 => 'NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED',
            4 => 'NET_SSH2_DISCONNECT_RESERVED',
            5 => 'NET_SSH2_DISCONNECT_MAC_ERROR',
            6 => 'NET_SSH2_DISCONNECT_COMPRESSION_ERROR',
            7 => 'NET_SSH2_DISCONNECT_SERVICE_NOT_AVAILABLE',
            8 => 'NET_SSH2_DISCONNECT_PROTOCOL_VERSION_NOT_SUPPORTED',
            9 => 'NET_SSH2_DISCONNECT_HOST_KEY_NOT_VERIFIABLE',
            10 => 'NET_SSH2_DISCONNECT_CONNECTION_LOST',
            11 => 'NET_SSH2_DISCONNECT_BY_APPLICATION',
            12 => 'NET_SSH2_DISCONNECT_TOO_MANY_CONNECTIONS',
            13 => 'NET_SSH2_DISCONNECT_AUTH_CANCELLED_BY_USER',
            14 => 'NET_SSH2_DISCONNECT_NO_MORE_AUTH_METHODS_AVAILABLE',
            15 => 'NET_SSH2_DISCONNECT_ILLEGAL_USER_NAME'
        );
        $this->channel_open_failure_reasons = array(
            1 => 'NET_SSH2_OPEN_ADMINISTRATIVELY_PROHIBITED'
        );
        $this->terminal_modes = array(
            0 => 'NET_SSH2_TTY_OP_END'
        );
        $this->channel_extended_data_type_codes = array(
            1 => 'NET_SSH2_EXTENDED_DATA_STDERR'
        );

        $this->_define_array(
            $this->message_numbers,
            $this->disconnect_reasons,
            $this->channel_open_failure_reasons,
            $this->terminal_modes,
            $this->channel_extended_data_type_codes,
            array(60 => 'NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQ'),
            array(60 => 'NET_SSH2_MSG_USERAUTH_PK_OK'),
            array(60 => 'NET_SSH2_MSG_USERAUTH_INFO_REQUEST',
                  61 => 'NET_SSH2_MSG_USERAUTH_INFO_RESPONSE')
        );

        $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838
        $this->fsock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->fsock) {
            user_error(rtrim("Cannot connect to $host. Error $errno. $errstr"), E_USER_NOTICE);
            return;
        }
        $elapsed = strtok(microtime(), ' ') + strtok('') - $start;

        if ($timeout - $elapsed <= 0) {
            user_error(rtrim("Cannot connect to $host. Timeout error"), E_USER_NOTICE);
            return;
        }

        $read = array($this->fsock);
        $write = $except = NULL;

        stream_set_blocking($this->fsock, false);

        // on windows this returns a "Warning: Invalid CRT parameters detected" error
        if (!@stream_select($read, $write, $except, $timeout - $elapsed)) {
            user_error(rtrim("Cannot connect to $host. Banner timeout"), E_USER_NOTICE);
            return;
        }

        stream_set_blocking($this->fsock, true);

        /* According to the SSH2 specs,

          "The server MAY send other lines of data before sending the version
           string.  Each line SHOULD be terminated by a Carriage Return and Line
           Feed.  Such lines MUST NOT begin with "SSH-", and SHOULD be encoded
           in ISO-10646 UTF-8 [RFC3629] (language is not specified).  Clients
           MUST be able to process such lines." */
        $temp = '';
        $extra = '';
        while (!feof($this->fsock) && !preg_match('#^SSH-(\d\.\d+)#', $temp, $matches)) {
            if (substr($temp, -2) == "\r\n") {
                $extra.= $temp;
                $temp = '';
            }
            $temp.= fgets($this->fsock, 255);
        }

        if (feof($this->fsock)) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }

        $ext = array();
        if (extension_loaded('mcrypt')) {
            $ext[] = 'mcrypt';
        }
        if (extension_loaded('gmp')) {
            $ext[] = 'gmp';
        } else if (extension_loaded('bcmath')) {
            $ext[] = 'bcmath';
        }

        if (!empty($ext)) {
            $this->identifier.= ' (' . implode(', ', $ext) . ')';
        }

        if (defined('NET_SSH2_LOGGING')) {
            $this->message_number_log[] = '<-';
            $this->message_number_log[] = '->';

            if (NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX) {
                $this->message_log[] = $temp;
                $this->message_log[] = $this->identifier . "\r\n";
            }
        }

        $this->server_identifier = trim($temp, "\r\n");
        if (!empty($extra)) {
            $this->errors[] = utf8_decode($extra);
        }

        if ($matches[1] != '1.99' && $matches[1] != '2.0') {
            user_error("Cannot connect to SSH $matches[1] servers", E_USER_NOTICE);
            return;
        }

        fputs($this->fsock, $this->identifier . "\r\n");

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return;
        }

        if (ord($response[0]) != NET_SSH2_MSG_KEXINIT) {
            user_error('Expected SSH_MSG_KEXINIT', E_USER_NOTICE);
            return;
        }

        if (!$this->_key_exchange($response)) {
            return;
        }

        $this->bitmap = NET_SSH2_MASK_CONSTRUCTOR;
    }

    /**
     * Key Exchange
     *
     * @param String $kexinit_payload_server
     * @access private
     */
    function _key_exchange($kexinit_payload_server)
    {
        static $kex_algorithms = array(
            'diffie-hellman-group1-sha1', // REQUIRED
            'diffie-hellman-group14-sha1' // REQUIRED
        );

        static $server_host_key_algorithms = array(
            'ssh-rsa', // RECOMMENDED  sign   Raw RSA Key
            'ssh-dss'  // REQUIRED     sign   Raw DSS Key
        );

        static $encryption_algorithms = array(
            // from <http://tools.ietf.org/html/rfc4345#section-4>:
            'arcfour256',
            'arcfour128',

            'arcfour',    // OPTIONAL          the ARCFOUR stream cipher with a 128-bit key

            'aes128-cbc', // RECOMMENDED       AES with a 128-bit key
            'aes192-cbc', // OPTIONAL          AES with a 192-bit key
            'aes256-cbc', // OPTIONAL          AES in CBC mode, with a 256-bit key

            // from <http://tools.ietf.org/html/rfc4344#section-4>:
            'aes128-ctr', // RECOMMENDED       AES (Rijndael) in SDCTR mode, with 128-bit key
            'aes192-ctr', // RECOMMENDED       AES with 192-bit key
            'aes256-ctr', // RECOMMENDED       AES with 256-bit key
            '3des-ctr',   // RECOMMENDED       Three-key 3DES in SDCTR mode

            '3des-cbc',   // REQUIRED          three-key 3DES in CBC mode
            'none'        // OPTIONAL          no encryption; NOT RECOMMENDED
        );

        static $mac_algorithms = array(
            'hmac-sha1-96', // RECOMMENDED     first 96 bits of HMAC-SHA1 (digest length = 12, key length = 20)
            'hmac-sha1',    // REQUIRED        HMAC-SHA1 (digest length = key length = 20)
            'hmac-md5-96',  // OPTIONAL        first 96 bits of HMAC-MD5 (digest length = 12, key length = 16)
            'hmac-md5',     // OPTIONAL        HMAC-MD5 (digest length = key length = 16)
            'none'          // OPTIONAL        no MAC; NOT RECOMMENDED
        );

        static $compression_algorithms = array(
            'none'   // REQUIRED        no compression
            //'zlib' // OPTIONAL        ZLIB (LZ77) compression
        );

        static $str_kex_algorithms, $str_server_host_key_algorithms,
               $encryption_algorithms_server_to_client, $mac_algorithms_server_to_client, $compression_algorithms_server_to_client,
               $encryption_algorithms_client_to_server, $mac_algorithms_client_to_server, $compression_algorithms_client_to_server;

        if (empty($str_kex_algorithms)) {
            $str_kex_algorithms = implode(',', $kex_algorithms);
            $str_server_host_key_algorithms = implode(',', $server_host_key_algorithms);
            $encryption_algorithms_server_to_client = $encryption_algorithms_client_to_server = implode(',', $encryption_algorithms);
            $mac_algorithms_server_to_client = $mac_algorithms_client_to_server = implode(',', $mac_algorithms);
            $compression_algorithms_server_to_client = $compression_algorithms_client_to_server = implode(',', $compression_algorithms);
        }

        $client_cookie = '';
        for ($i = 0; $i < 16; $i++) {
            $client_cookie.= chr(crypt_random(0, 255));
        }

        $response = $kexinit_payload_server;
        $this->_string_shift($response, 1); // skip past the message number (it should be SSH_MSG_KEXINIT)
        $server_cookie = $this->_string_shift($response, 16);

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->kex_algorithms = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->server_host_key_algorithms = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->encryption_algorithms_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->encryption_algorithms_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->mac_algorithms_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->mac_algorithms_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->compression_algorithms_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->compression_algorithms_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->languages_client_to_server = explode(',', $this->_string_shift($response, $temp['length']));

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->languages_server_to_client = explode(',', $this->_string_shift($response, $temp['length']));

        extract(unpack('Cfirst_kex_packet_follows', $this->_string_shift($response, 1)));
        $first_kex_packet_follows = $first_kex_packet_follows != 0;

        // the sending of SSH2_MSG_KEXINIT could go in one of two places.  this is the second place.
        $kexinit_payload_client = pack('Ca*Na*Na*Na*Na*Na*Na*Na*Na*Na*Na*CN',
            NET_SSH2_MSG_KEXINIT, $client_cookie, strlen($str_kex_algorithms), $str_kex_algorithms,
            strlen($str_server_host_key_algorithms), $str_server_host_key_algorithms, strlen($encryption_algorithms_client_to_server),
            $encryption_algorithms_client_to_server, strlen($encryption_algorithms_server_to_client), $encryption_algorithms_server_to_client,
            strlen($mac_algorithms_client_to_server), $mac_algorithms_client_to_server, strlen($mac_algorithms_server_to_client),
            $mac_algorithms_server_to_client, strlen($compression_algorithms_client_to_server), $compression_algorithms_client_to_server,
            strlen($compression_algorithms_server_to_client), $compression_algorithms_server_to_client, 0, '', 0, '',
            0, 0
        );

        if (!$this->_send_binary_packet($kexinit_payload_client)) {
            return false;
        }
        // here ends the second place.

        // we need to decide upon the symmetric encryption algorithms before we do the diffie-hellman key exchange
        for ($i = 0; $i < count($encryption_algorithms) && !in_array($encryption_algorithms[$i], $this->encryption_algorithms_server_to_client); $i++);
        if ($i == count($encryption_algorithms)) {
            user_error('No compatible server to client encryption algorithms found', E_USER_NOTICE);
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        // we don't initialize any crypto-objects, yet - we do that, later. for now, we need the lengths to make the
        // diffie-hellman key exchange as fast as possible
        $decrypt = $encryption_algorithms[$i];
        switch ($decrypt) {
            case '3des-cbc':
            case '3des-ctr':
                $decryptKeyLength = 24; // eg. 192 / 8
                break;
            case 'aes256-cbc':
            case 'aes256-ctr':
                $decryptKeyLength = 32; // eg. 256 / 8
                break;
            case 'aes192-cbc':
            case 'aes192-ctr':
                $decryptKeyLength = 24; // eg. 192 / 8
                break;
            case 'aes128-cbc':
            case 'aes128-ctr':
                $decryptKeyLength = 16; // eg. 128 / 8
                break;
            case 'arcfour':
            case 'arcfour128':
                $decryptKeyLength = 16; // eg. 128 / 8
                break;
            case 'arcfour256':
                $decryptKeyLength = 32; // eg. 128 / 8
                break;
            case 'none';
                $decryptKeyLength = 0;
        }

        for ($i = 0; $i < count($encryption_algorithms) && !in_array($encryption_algorithms[$i], $this->encryption_algorithms_client_to_server); $i++);
        if ($i == count($encryption_algorithms)) {
            user_error('No compatible client to server encryption algorithms found', E_USER_NOTICE);
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        $encrypt = $encryption_algorithms[$i];
        switch ($encrypt) {
            case '3des-cbc':
            case '3des-ctr':
                $encryptKeyLength = 24;
                break;
            case 'aes256-cbc':
            case 'aes256-ctr':
                $encryptKeyLength = 32;
                break;
            case 'aes192-cbc':
            case 'aes192-ctr':
                $encryptKeyLength = 24;
                break;
            case 'aes128-cbc':
            case 'aes128-ctr':
                $encryptKeyLength = 16;
                break;
            case 'arcfour':
            case 'arcfour128':
                $encryptKeyLength = 16;
                break;
            case 'arcfour256':
                $encryptKeyLength = 32;
                break;
            case 'none';
                $encryptKeyLength = 0;
        }

        $keyLength = $decryptKeyLength > $encryptKeyLength ? $decryptKeyLength : $encryptKeyLength;

        // through diffie-hellman key exchange a symmetric key is obtained
        for ($i = 0; $i < count($kex_algorithms) && !in_array($kex_algorithms[$i], $this->kex_algorithms); $i++);
        if ($i == count($kex_algorithms)) {
            user_error('No compatible key exchange algorithms found', E_USER_NOTICE);
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        switch ($kex_algorithms[$i]) {
            // see http://tools.ietf.org/html/rfc2409#section-6.2 and 
            // http://tools.ietf.org/html/rfc2412, appendex E
            case 'diffie-hellman-group1-sha1':
                $p = pack('H256', 'FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E088A67CC74' . 
                                  '020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B302B0A6DF25F1437' . 
                                  '4FE1356D6D51C245E485B576625E7EC6F44C42E9A637ED6B0BFF5CB6F406B7ED' . 
                                  'EE386BFB5A899FA5AE9F24117C4B1FE649286651ECE65381FFFFFFFFFFFFFFFF');
                $keyLength = $keyLength < 160 ? $keyLength : 160;
                $hash = 'sha1';
                break;
            // see http://tools.ietf.org/html/rfc3526#section-3
            case 'diffie-hellman-group14-sha1':
                $p = pack('H512', 'FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD129024E088A67CC74' . 
                                  '020BBEA63B139B22514A08798E3404DDEF9519B3CD3A431B302B0A6DF25F1437' . 
                                  '4FE1356D6D51C245E485B576625E7EC6F44C42E9A637ED6B0BFF5CB6F406B7ED' . 
                                  'EE386BFB5A899FA5AE9F24117C4B1FE649286651ECE45B3DC2007CB8A163BF05' . 
                                  '98DA48361C55D39A69163FA8FD24CF5F83655D23DCA3AD961C62F356208552BB' . 
                                  '9ED529077096966D670C354E4ABC9804F1746C08CA18217C32905E462E36CE3B' . 
                                  'E39E772C180E86039B2783A2EC07A28FB5C55DF06F4C52C9DE2BCBF695581718' . 
                                  '3995497CEA956AE515D2261898FA051015728E5A8AACAA68FFFFFFFFFFFFFFFF');
                $keyLength = $keyLength < 160 ? $keyLength : 160;
                $hash = 'sha1';
        }

        $p = new Math_BigInteger($p, 256);
        //$q = $p->bitwise_rightShift(1);

        /* To increase the speed of the key exchange, both client and server may
           reduce the size of their private exponents.  It should be at least
           twice as long as the key material that is generated from the shared
           secret.  For more details, see the paper by van Oorschot and Wiener
           [VAN-OORSCHOT].

           -- http://tools.ietf.org/html/rfc4419#section-6.2 */
        $q = new Math_BigInteger(1);
        $q = $q->bitwise_leftShift(2 * $keyLength);
        $q = $q->subtract(new Math_BigInteger(1));

        $g = new Math_BigInteger(2);
        $x = new Math_BigInteger();
        $x->setRandomGenerator('crypt_random');
        $x = $x->random(new Math_BigInteger(1), $q);
        $e = $g->modPow($x, $p);

        $eBytes = $e->toBytes(true);
        $data = pack('CNa*', NET_SSH2_MSG_KEXDH_INIT, strlen($eBytes), $eBytes);

        if (!$this->_send_binary_packet($data)) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }
        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        if ($type != NET_SSH2_MSG_KEXDH_REPLY) {
            user_error('Expected SSH_MSG_KEXDH_REPLY', E_USER_NOTICE);
            return false;
        }

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->server_public_host_key = $server_public_host_key = $this->_string_shift($response, $temp['length']);

        $temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
        $public_key_format = $this->_string_shift($server_public_host_key, $temp['length']);

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $fBytes = $this->_string_shift($response, $temp['length']);
        $f = new Math_BigInteger($fBytes, -256);

        $temp = unpack('Nlength', $this->_string_shift($response, 4));
        $this->signature = $this->_string_shift($response, $temp['length']);

        $temp = unpack('Nlength', $this->_string_shift($this->signature, 4));
        $this->signature_format = $this->_string_shift($this->signature, $temp['length']);

        $key = $f->modPow($x, $p);
        $keyBytes = $key->toBytes(true);

        $this->exchange_hash = pack('Na*Na*Na*Na*Na*Na*Na*Na*',
            strlen($this->identifier), $this->identifier, strlen($this->server_identifier), $this->server_identifier,
            strlen($kexinit_payload_client), $kexinit_payload_client, strlen($kexinit_payload_server),
            $kexinit_payload_server, strlen($this->server_public_host_key), $this->server_public_host_key, strlen($eBytes),
            $eBytes, strlen($fBytes), $fBytes, strlen($keyBytes), $keyBytes
        );

        $this->exchange_hash = pack('H*', $hash($this->exchange_hash));

        if ($this->session_id === false) {
            $this->session_id = $this->exchange_hash;
        }

        for ($i = 0; $i < count($server_host_key_algorithms) && !in_array($server_host_key_algorithms[$i], $this->server_host_key_algorithms); $i++);
        if ($i == count($server_host_key_algorithms)) {
            user_error('No compatible server host key algorithms found', E_USER_NOTICE);
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        if ($public_key_format != $server_host_key_algorithms[$i] || $this->signature_format != $server_host_key_algorithms[$i]) {
            user_error('Sever Host Key Algorithm Mismatch', E_USER_NOTICE);
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        $packet = pack('C',
            NET_SSH2_MSG_NEWKEYS
        );

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $response = $this->_get_binary_packet();

        if ($response === false) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        if ($type != NET_SSH2_MSG_NEWKEYS) {
            user_error('Expected SSH_MSG_NEWKEYS', E_USER_NOTICE);
            return false;
        }

        switch ($encrypt) {
            case '3des-cbc':
                $this->encrypt = new Crypt_TripleDES();
                // $this->encrypt_block_size = 64 / 8 == the default
                break;
            case '3des-ctr':
                $this->encrypt = new Crypt_TripleDES(CRYPT_DES_MODE_CTR);
                // $this->encrypt_block_size = 64 / 8 == the default
                break;
            case 'aes256-cbc':
            case 'aes192-cbc':
            case 'aes128-cbc':
                $this->encrypt = new Crypt_AES();
                $this->encrypt_block_size = 16; // eg. 128 / 8
                break;
            case 'aes256-ctr':
            case 'aes192-ctr':
            case 'aes128-ctr':
                $this->encrypt = new Crypt_AES(CRYPT_AES_MODE_CTR);
                $this->encrypt_block_size = 16; // eg. 128 / 8
                break;
            case 'arcfour':
            case 'arcfour128':
            case 'arcfour256':
                $this->encrypt = new Crypt_RC4();
                break;
            case 'none';
                //$this->encrypt = new Crypt_Null();
        }

        switch ($decrypt) {
            case '3des-cbc':
                $this->decrypt = new Crypt_TripleDES();
                break;
            case '3des-ctr':
                $this->decrypt = new Crypt_TripleDES(CRYPT_DES_MODE_CTR);
                break;
            case 'aes256-cbc':
            case 'aes192-cbc':
            case 'aes128-cbc':
                $this->decrypt = new Crypt_AES();
                $this->decrypt_block_size = 16;
                break;
            case 'aes256-ctr':
            case 'aes192-ctr':
            case 'aes128-ctr':
                $this->decrypt = new Crypt_AES(CRYPT_AES_MODE_CTR);
                $this->decrypt_block_size = 16;
                break;
            case 'arcfour':
            case 'arcfour128':
            case 'arcfour256':
                $this->decrypt = new Crypt_RC4();
                break;
            case 'none';
                //$this->decrypt = new Crypt_Null();
        }

        $keyBytes = pack('Na*', strlen($keyBytes), $keyBytes);

        if ($this->encrypt) {
            $this->encrypt->enableContinuousBuffer();
            $this->encrypt->disablePadding();

            $iv = pack('H*', $hash($keyBytes . $this->exchange_hash . 'A' . $this->session_id));
            while ($this->encrypt_block_size > strlen($iv)) {
                $iv.= pack('H*', $hash($keyBytes . $this->exchange_hash . $iv));
            }
            $this->encrypt->setIV(substr($iv, 0, $this->encrypt_block_size));

            $key = pack('H*', $hash($keyBytes . $this->exchange_hash . 'C' . $this->session_id));
            while ($encryptKeyLength > strlen($key)) {
                $key.= pack('H*', $hash($keyBytes . $this->exchange_hash . $key));
            }
            $this->encrypt->setKey(substr($key, 0, $encryptKeyLength));
        }

        if ($this->decrypt) {
            $this->decrypt->enableContinuousBuffer();
            $this->decrypt->disablePadding();

            $iv = pack('H*', $hash($keyBytes . $this->exchange_hash . 'B' . $this->session_id));
            while ($this->decrypt_block_size > strlen($iv)) {
                $iv.= pack('H*', $hash($keyBytes . $this->exchange_hash . $iv));
            }
            $this->decrypt->setIV(substr($iv, 0, $this->decrypt_block_size));

            $key = pack('H*', $hash($keyBytes . $this->exchange_hash . 'D' . $this->session_id));
            while ($decryptKeyLength > strlen($key)) {
                $key.= pack('H*', $hash($keyBytes . $this->exchange_hash . $key));
            }
            $this->decrypt->setKey(substr($key, 0, $decryptKeyLength));
        }

        /* The "arcfour128" algorithm is the RC4 cipher, as described in
           [SCHNEIER], using a 128-bit key.  The first 1536 bytes of keystream
           generated by the cipher MUST be discarded, and the first byte of the
           first encrypted packet MUST be encrypted using the 1537th byte of
           keystream.

           -- http://tools.ietf.org/html/rfc4345#section-4 */
        if ($encrypt == 'arcfour128' || $encrypt == 'arcfour256') {
            $this->encrypt->encrypt(str_repeat("\0", 1536));
        }
        if ($decrypt == 'arcfour128' || $decrypt == 'arcfour256') {
            $this->decrypt->decrypt(str_repeat("\0", 1536));
        }

        for ($i = 0; $i < count($mac_algorithms) && !in_array($mac_algorithms[$i], $this->mac_algorithms_client_to_server); $i++);
        if ($i == count($mac_algorithms)) {
            user_error('No compatible client to server message authentication algorithms found', E_USER_NOTICE);
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        $createKeyLength = 0; // ie. $mac_algorithms[$i] == 'none'
        switch ($mac_algorithms[$i]) {
            case 'hmac-sha1':
                $this->hmac_create = new Crypt_Hash('sha1');
                $createKeyLength = 20;
                break;
            case 'hmac-sha1-96':
                $this->hmac_create = new Crypt_Hash('sha1-96');
                $createKeyLength = 20;
                break;
            case 'hmac-md5':
                $this->hmac_create = new Crypt_Hash('md5');
                $createKeyLength = 16;
                break;
            case 'hmac-md5-96':
                $this->hmac_create = new Crypt_Hash('md5-96');
                $createKeyLength = 16;
        }

        for ($i = 0; $i < count($mac_algorithms) && !in_array($mac_algorithms[$i], $this->mac_algorithms_server_to_client); $i++);
        if ($i == count($mac_algorithms)) {
            user_error('No compatible server to client message authentication algorithms found', E_USER_NOTICE);
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }

        $checkKeyLength = 0;
        $this->hmac_size = 0;
        switch ($mac_algorithms[$i]) {
            case 'hmac-sha1':
                $this->hmac_check = new Crypt_Hash('sha1');
                $checkKeyLength = 20;
                $this->hmac_size = 20;
                break;
            case 'hmac-sha1-96':
                $this->hmac_check = new Crypt_Hash('sha1-96');
                $checkKeyLength = 20;
                $this->hmac_size = 12;
                break;
            case 'hmac-md5':
                $this->hmac_check = new Crypt_Hash('md5');
                $checkKeyLength = 16;
                $this->hmac_size = 16;
                break;
            case 'hmac-md5-96':
                $this->hmac_check = new Crypt_Hash('md5-96');
                $checkKeyLength = 16;
                $this->hmac_size = 12;
        }

        $key = pack('H*', $hash($keyBytes . $this->exchange_hash . 'E' . $this->session_id));
        while ($createKeyLength > strlen($key)) {
            $key.= pack('H*', $hash($keyBytes . $this->exchange_hash . $key));
        }
        $this->hmac_create->setKey(substr($key, 0, $createKeyLength));

        $key = pack('H*', $hash($keyBytes . $this->exchange_hash . 'F' . $this->session_id));
        while ($checkKeyLength > strlen($key)) {
            $key.= pack('H*', $hash($keyBytes . $this->exchange_hash . $key));
        }
        $this->hmac_check->setKey(substr($key, 0, $checkKeyLength));

        for ($i = 0; $i < count($compression_algorithms) && !in_array($compression_algorithms[$i], $this->compression_algorithms_server_to_client); $i++);
        if ($i == count($compression_algorithms)) {
            user_error('No compatible server to client compression algorithms found', E_USER_NOTICE);
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }
        $this->decompress = $compression_algorithms[$i] == 'zlib';

        for ($i = 0; $i < count($compression_algorithms) && !in_array($compression_algorithms[$i], $this->compression_algorithms_client_to_server); $i++);
        if ($i == count($compression_algorithms)) {
            user_error('No compatible client to server compression algorithms found', E_USER_NOTICE);
            return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
        }
        $this->compress = $compression_algorithms[$i] == 'zlib';

        return true;
    }

    /**
     * Login
     *
     * The $password parameter can be a plaintext password or a Crypt_RSA object.
     *
     * @param String $username
     * @param optional String $password
     * @return Boolean
     * @access public
     * @internal It might be worthwhile, at some point, to protect against {@link http://tools.ietf.org/html/rfc4251#section-9.3.9 traffic analysis}
     *           by sending dummy SSH_MSG_IGNORE messages.
     */
    function login($username, $password = '')
    {
        if (!($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR)) {
            return false;
        }

        $packet = pack('CNa*',
            NET_SSH2_MSG_SERVICE_REQUEST, strlen('ssh-userauth'), 'ssh-userauth'
        );

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        if ($type != NET_SSH2_MSG_SERVICE_ACCEPT) {
            user_error('Expected SSH_MSG_SERVICE_ACCEPT', E_USER_NOTICE);
            return false;
        }

        // although PHP5's get_class() preserves the case, PHP4's does not
        if (is_object($password) && strtolower(get_class($password)) == 'crypt_rsa') {
            return $this->_privatekey_login($username, $password);
        }

        $packet = pack('CNa*Na*Na*CNa*',
            NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
            strlen('password'), 'password', 0, strlen($password), $password
        );

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        // remove the username and password from the last logged packet
        if (defined('NET_SSH2_LOGGING') && NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX) {
            $packet = pack('CNa*Na*Na*CNa*',
                NET_SSH2_MSG_USERAUTH_REQUEST, strlen('username'), 'username', strlen('ssh-connection'), 'ssh-connection',
                strlen('password'), 'password', 0, strlen('password'), 'password'
            );
            $this->message_log[count($this->message_log) - 1] = $packet;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        switch ($type) {
            case NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQ: // in theory, the password can be changed
                if (defined('NET_SSH2_LOGGING')) {
                    $this->message_number_log[count($this->message_number_log) - 1] = 'NET_SSH2_MSG_USERAUTH_PASSWD_CHANGEREQ';
                }
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->errors[] = 'SSH_MSG_USERAUTH_PASSWD_CHANGEREQ: ' . utf8_decode($this->_string_shift($response, $length));
                return $this->_disconnect(NET_SSH2_DISCONNECT_AUTH_CANCELLED_BY_USER);
            case NET_SSH2_MSG_USERAUTH_FAILURE:
                // can we use keyboard-interactive authentication?  if not then either the login is bad or the server employees
                // multi-factor authentication
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $auth_methods = explode(',', $this->_string_shift($response, $length));
                if (in_array('keyboard-interactive', $auth_methods)) {
                    if ($this->_keyboard_interactive_login($username, $password)) {
                        $this->bitmap |= NET_SSH2_MASK_LOGIN;
                        return true;
                    }
                    return false;
                }
                return false;
            case NET_SSH2_MSG_USERAUTH_SUCCESS:
                $this->bitmap |= NET_SSH2_MASK_LOGIN;
                return true;
        }

        return false;
    }

    /**
     * Login via keyboard-interactive authentication
     *
     * See {@link http://tools.ietf.org/html/rfc4256 RFC4256} for details.  This is not a full-featured keyboard-interactive authenticator.
     *
     * @param String $username
     * @param String $password
     * @return Boolean
     * @access private
     */
    function _keyboard_interactive_login($username, $password)
    {
        $packet = pack('CNa*Na*Na*Na*Na*', 
            NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
            strlen('keyboard-interactive'), 'keyboard-interactive', 0, '', 0, ''
        );

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        return $this->_keyboard_interactive_process($password);
    }

    /**
     * Handle the keyboard-interactive requests / responses.
     *
     * @param String $responses...
     * @return Boolean
     * @access private
     */
    function _keyboard_interactive_process()
    {
        $responses = func_get_args();

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        switch ($type) {
            case NET_SSH2_MSG_USERAUTH_INFO_REQUEST:
                // see http://tools.ietf.org/html/rfc4256#section-3.2
                if (defined('NET_SSH2_LOGGING')) {
                    $this->message_number_log[count($this->message_number_log) - 1] = str_replace(
                        'UNKNOWN',
                        'NET_SSH2_MSG_USERAUTH_INFO_REQUEST',
                        $this->message_number_log[count($this->message_number_log) - 1]
                    );
                }

                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->_string_shift($response, $length); // name; may be empty
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->_string_shift($response, $length); // instruction; may be empty
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->_string_shift($response, $length); // language tag; may be empty
                extract(unpack('Nnum_prompts', $this->_string_shift($response, 4)));
                /*
                for ($i = 0; $i < $num_prompts; $i++) {
                    extract(unpack('Nlength', $this->_string_shift($response, 4)));
                    // prompt - ie. "Password: "; must not be empty
                    $this->_string_shift($response, $length);
                    $echo = $this->_string_shift($response) != chr(0);
                }
                */

                /*
                   After obtaining the requested information from the user, the client
                   MUST respond with an SSH_MSG_USERAUTH_INFO_RESPONSE message.
                */
                // see http://tools.ietf.org/html/rfc4256#section-3.4
                $packet = $logged = pack('CN', NET_SSH2_MSG_USERAUTH_INFO_RESPONSE, count($responses));
                for ($i = 0; $i < count($responses); $i++) {
                    $packet.= pack('Na*', strlen($responses[$i]), $responses[$i]);
                    $logged.= pack('Na*', strlen('dummy-answer'), 'dummy-answer');
                }

                if (!$this->_send_binary_packet($packet)) {
                    return false;
                }

                if (defined('NET_SSH2_LOGGING')) {
                    $this->message_number_log[count($this->message_number_log) - 1] = str_replace(
                        'UNKNOWN',
                        'NET_SSH2_MSG_USERAUTH_INFO_RESPONSE',
                        $this->message_number_log[count($this->message_number_log) - 1]
                    );
                    $this->message_log[count($this->message_log) - 1] = $logged;
                }

                /*
                   After receiving the response, the server MUST send either an
                   SSH_MSG_USERAUTH_SUCCESS, SSH_MSG_USERAUTH_FAILURE, or another
                   SSH_MSG_USERAUTH_INFO_REQUEST message.
                */
                // maybe phpseclib should force close the connection after x request / responses?  unless something like that is done
                // there could be an infinite loop of request / responses.
                return $this->_keyboard_interactive_process();
            case NET_SSH2_MSG_USERAUTH_SUCCESS:
                return true;
            case NET_SSH2_MSG_USERAUTH_FAILURE:
                return false;
        }

        return false;
    }

    /**
     * Login with an RSA private key
     *
     * @param String $username
     * @param Crypt_RSA $password
     * @return Boolean
     * @access private
     * @internal It might be worthwhile, at some point, to protect against {@link http://tools.ietf.org/html/rfc4251#section-9.3.9 traffic analysis}
     *           by sending dummy SSH_MSG_IGNORE messages.
     */
    function _privatekey_login($username, $privatekey)
    {
        // see http://tools.ietf.org/html/rfc4253#page-15
        $publickey = $privatekey->getPublicKey(CRYPT_RSA_PUBLIC_FORMAT_RAW);
        if ($publickey === false) {
            return false;
        }

        $publickey = array(
            'e' => $publickey['e']->toBytes(true),
            'n' => $publickey['n']->toBytes(true)
        );
        $publickey = pack('Na*Na*Na*',
            strlen('ssh-rsa'), 'ssh-rsa', strlen($publickey['e']), $publickey['e'], strlen($publickey['n']), $publickey['n']
        );

        $part1 = pack('CNa*Na*Na*',
            NET_SSH2_MSG_USERAUTH_REQUEST, strlen($username), $username, strlen('ssh-connection'), 'ssh-connection',
            strlen('publickey'), 'publickey'
        );
        $part2 = pack('Na*Na*', strlen('ssh-rsa'), 'ssh-rsa', strlen($publickey), $publickey);

        $packet = $part1 . chr(0) . $part2;
        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        switch ($type) {
            case NET_SSH2_MSG_USERAUTH_FAILURE:
                extract(unpack('Nlength', $this->_string_shift($response, 4)));
                $this->errors[] = 'SSH_MSG_USERAUTH_FAILURE: ' . $this->_string_shift($response, $length);
                return $this->_disconnect(NET_SSH2_DISCONNECT_AUTH_CANCELLED_BY_USER);
            case NET_SSH2_MSG_USERAUTH_PK_OK:
                // we'll just take it on faith that the public key blob and the public key algorithm name are as
                // they should be
                if (defined('NET_SSH2_LOGGING')) {
                    $this->message_number_log[count($this->message_number_log) - 1] = str_replace(
                        'UNKNOWN',
                        'NET_SSH2_MSG_USERAUTH_PK_OK',
                        $this->message_number_log[count($this->message_number_log) - 1]
                    );
                }
        }

        $packet = $part1 . chr(1) . $part2;
        $privatekey->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
        $signature = $privatekey->sign(pack('Na*a*', strlen($this->session_id), $this->session_id, $packet));
        $signature = pack('Na*Na*', strlen('ssh-rsa'), 'ssh-rsa', strlen($signature), $signature);
        $packet.= pack('Na*', strlen($signature), $signature);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Ctype', $this->_string_shift($response, 1)));

        switch ($type) {
            case NET_SSH2_MSG_USERAUTH_FAILURE:
                // either the login is bad or the server employees multi-factor authentication
                return false;
            case NET_SSH2_MSG_USERAUTH_SUCCESS:
                $this->bitmap |= NET_SSH2_MASK_LOGIN;
                return true;
        }

        return false;
    }

    /**
     * Set Timeout
     *
     * $ssh->exec('ping 127.0.0.1'); on a Linux host will never return and will run indefinitely.  setTimeout() makes it so it'll timeout.
     * Setting $timeout to false or 0 will mean there is no timeout.
     *
     * @param Mixed $timeout
     */
    function setTimeout($timeout)
    {
        $this->timeout = $this->curTimeout = $timeout;
    }

    /**
     * Execute Command
     *
     * If $block is set to false then Net_SSH2::_get_channel_packet(NET_SSH2_CHANNEL_EXEC) will need to be called manually.
     * In all likelihood, this is not a feature you want to be taking advantage of.
     *
     * @param String $command
     * @param optional Boolean $block
     * @return String
     * @access public
     */
    function exec($command, $block = true)
    {
        $this->curTimeout = $this->timeout;

        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            return false;
        }

        // RFC4254 defines the (client) window size as "bytes the other party can send before it must wait for the window to
        // be adjusted".  0x7FFFFFFF is, at 4GB, the max size.  technically, it should probably be decremented, but, 
        // honestly, if you're transfering more than 4GB, you probably shouldn't be using phpseclib, anyway.
        // see http://tools.ietf.org/html/rfc4254#section-5.2 for more info
        $this->window_size_client_to_server[NET_SSH2_CHANNEL_EXEC] = 0x7FFFFFFF;
        // 0x8000 is the maximum max packet size, per http://tools.ietf.org/html/rfc4253#section-6.1, although since PuTTy
        // uses 0x4000, that's what will be used here, as well.
        $packet_size = 0x4000;

        $packet = pack('CNa*N3',
            NET_SSH2_MSG_CHANNEL_OPEN, strlen('session'), 'session', NET_SSH2_CHANNEL_EXEC, $this->window_size_client_to_server[NET_SSH2_CHANNEL_EXEC], $packet_size);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_EXEC] = NET_SSH2_MSG_CHANNEL_OPEN;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_EXEC);
        if ($response === false) {
            return false;
        }

        // sending a pty-req SSH_MSG_CHANNEL_REQUEST message is unnecessary and, in fact, in most cases, slows things
        // down.  the one place where it might be desirable is if you're doing something like Net_SSH2::exec('ping localhost &').
        // with a pty-req SSH_MSG_CHANNEL_REQUEST, exec() will return immediately and the ping process will then
        // then immediately terminate.  without such a request exec() will loop indefinitely.  the ping process won't end but
        // neither will your script.

        // although, in theory, the size of SSH_MSG_CHANNEL_REQUEST could exceed the maximum packet size established by
        // SSH_MSG_CHANNEL_OPEN_CONFIRMATION, RFC4254#section-5.1 states that the "maximum packet size" refers to the 
        // "maximum size of an individual data packet". ie. SSH_MSG_CHANNEL_DATA.  RFC4254#section-5.2 corroborates.
        $packet = pack('CNNa*CNa*',
            NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_EXEC], strlen('exec'), 'exec', 1, strlen($command), $command);
        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_EXEC] = NET_SSH2_MSG_CHANNEL_REQUEST;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_EXEC);
        if ($response === false) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_EXEC] = NET_SSH2_MSG_CHANNEL_DATA;

        if (!$block) {
            return true;
        }

        $output = '';
        while (true) {
            $temp = $this->_get_channel_packet(NET_SSH2_CHANNEL_EXEC);
            switch (true) {
                case $temp === true:
                    return $output;
                case $temp === false:
                    return false;
                default:
                    $output.= $temp;
            }
        }
    }

    /**
     * Creates an interactive shell
     *
     * @see Net_SSH2::read()
     * @see Net_SSH2::write()
     * @return Boolean
     * @access private
     */
    function _initShell()
    {
        $this->window_size_client_to_server[NET_SSH2_CHANNEL_SHELL] = 0x7FFFFFFF;
        $packet_size = 0x4000;

        $packet = pack('CNa*N3',
            NET_SSH2_MSG_CHANNEL_OPEN, strlen('session'), 'session', NET_SSH2_CHANNEL_SHELL, $this->window_size_client_to_server[NET_SSH2_CHANNEL_SHELL], $packet_size);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_SHELL] = NET_SSH2_MSG_CHANNEL_OPEN;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SHELL);
        if ($response === false) {
            return false;
        }

        $terminal_modes = pack('C', NET_SSH2_TTY_OP_END);
        $packet = pack('CNNa*CNa*N5a*',
            NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_SHELL], strlen('pty-req'), 'pty-req', 1, strlen('vt100'), 'vt100',
            80, 24, 0, 0, strlen($terminal_modes), $terminal_modes);

        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $response = $this->_get_binary_packet();
        if ($response === false) {
            user_error('Connection closed by server', E_USER_NOTICE);
            return false;
        }

        list(, $type) = unpack('C', $this->_string_shift($response, 1));

        switch ($type) {
            case NET_SSH2_MSG_CHANNEL_SUCCESS:
                break;
            case NET_SSH2_MSG_CHANNEL_FAILURE:
            default:
                user_error('Unable to request pseudo-terminal', E_USER_NOTICE);
                return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
        }

        $packet = pack('CNNa*C',
            NET_SSH2_MSG_CHANNEL_REQUEST, $this->server_channels[NET_SSH2_CHANNEL_SHELL], strlen('shell'), 'shell', 1);
        if (!$this->_send_binary_packet($packet)) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_SHELL] = NET_SSH2_MSG_CHANNEL_REQUEST;

        $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SHELL);
        if ($response === false) {
            return false;
        }

        $this->channel_status[NET_SSH2_CHANNEL_SHELL] = NET_SSH2_MSG_CHANNEL_DATA;

        $this->bitmap |= NET_SSH2_MASK_SHELL;

        return true;
    }

    /**
     * Returns the output of an interactive shell
     *
     * Returns when there's a match for $expect, which can take the form of a string literal or,
     * if $mode == NET_SSH2_READ_REGEX, a regular expression.
     *
     * @see Net_SSH2::read()
     * @param String $expect
     * @param Integer $mode
     * @return String
     * @access public
     */
    function read($expect = '', $mode = NET_SSH2_READ_SIMPLE)
    {
        $this->curTimeout = $this->timeout;

        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            user_error('Operation disallowed prior to login()', E_USER_NOTICE);
            return false;
        }

        if (!($this->bitmap & NET_SSH2_MASK_SHELL) && !$this->_initShell()) {
            user_error('Unable to initiate an interactive shell session', E_USER_NOTICE);
            return false;
        }

        $match = $expect;
        while (true) {
            if ($mode == NET_SSH2_READ_REGEX) {
                preg_match($expect, $this->interactiveBuffer, $matches);
                $match = $matches[0];
            }
            $pos = !empty($match) ? strpos($this->interactiveBuffer, $match) : false;
            if ($pos !== false) {
                return $this->_string_shift($this->interactiveBuffer, $pos + strlen($match));
            }
            $response = $this->_get_channel_packet(NET_SSH2_CHANNEL_SHELL);
            if (is_bool($response)) {
                return $response ? $this->_string_shift($this->interactiveBuffer, strlen($this->interactiveBuffer)) : false;
            }

            $this->interactiveBuffer.= $response;
        }
    }

    /**
     * Inputs a command into an interactive shell.
     *
     * @see Net_SSH1::interactiveWrite()
     * @param String $cmd
     * @return Boolean
     * @access public
     */
    function write($cmd)
    {
        if (!($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            user_error('Operation disallowed prior to login()', E_USER_NOTICE);
            return false;
        }

        if (!($this->bitmap & NET_SSH2_MASK_SHELL) && !$this->_initShell()) {
            user_error('Unable to initiate an interactive shell session', E_USER_NOTICE);
            return false;
        }

        return $this->_send_channel_packet(NET_SSH2_CHANNEL_SHELL, $cmd);
    }

    /**
     * Disconnect
     *
     * @access public
     */
    function disconnect()
    {
        $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
        if (isset($this->realtime_log_file) && is_resource($this->realtime_log_file)) {
            fclose($this->realtime_log_file);
        }
    }

    /**
     * Destructor.
     *
     * Will be called, automatically, if you're supporting just PHP5.  If you're supporting PHP4, you'll need to call
     * disconnect().
     *
     * @access public
     */
    function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Gets Binary Packets
     *
     * See '6. Binary Packet Protocol' of rfc4253 for more info.
     *
     * @see Net_SSH2::_send_binary_packet()
     * @return String
     * @access private
     */
    function _get_binary_packet()
    {
        if (!is_resource($this->fsock) || feof($this->fsock)) {
            user_error('Connection closed prematurely', E_USER_NOTICE);
            return false;
        }

        $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838
        $raw = fread($this->fsock, $this->decrypt_block_size);
        $stop = strtok(microtime(), ' ') + strtok('');

        if (empty($raw)) {
            return '';
        }

        if ($this->decrypt !== false) {
            $raw = $this->decrypt->decrypt($raw);
        }
        if ($raw === false) {
            user_error('Unable to decrypt content', E_USER_NOTICE);
            return false;
        }

        extract(unpack('Npacket_length/Cpadding_length', $this->_string_shift($raw, 5)));

        $remaining_length = $packet_length + 4 - $this->decrypt_block_size;
        $buffer = '';
        while ($remaining_length > 0) {
            $temp = fread($this->fsock, $remaining_length);
            $buffer.= $temp;
            $remaining_length-= strlen($temp);
        }
        if (!empty($buffer)) {
            $raw.= $this->decrypt !== false ? $this->decrypt->decrypt($buffer) : $buffer;
            $buffer = $temp = '';
        }

        $payload = $this->_string_shift($raw, $packet_length - $padding_length - 1);
        $padding = $this->_string_shift($raw, $padding_length); // should leave $raw empty

        if ($this->hmac_check !== false) {
            $hmac = fread($this->fsock, $this->hmac_size);
            if ($hmac != $this->hmac_check->hash(pack('NNCa*', $this->get_seq_no, $packet_length, $padding_length, $payload . $padding))) {
                user_error('Invalid HMAC', E_USER_NOTICE);
                return false;
            }
        }

        //if ($this->decompress) {
        //    $payload = gzinflate(substr($payload, 2));
        //}

        $this->get_seq_no++;

        if (defined('NET_SSH2_LOGGING')) {
            $message_number = isset($this->message_numbers[ord($payload[0])]) ? $this->message_numbers[ord($payload[0])] : 'UNKNOWN (' . ord($payload[0]) . ')';
            $message_number = '<- ' . $message_number .
                              ' (' . round($stop - $start, 4) . 's)';
            $this->_append_log($message_number, $payload);
        }

        return $this->_filter($payload);
    }

    /**
     * Filter Binary Packets
     *
     * Because some binary packets need to be ignored...
     *
     * @see Net_SSH2::_get_binary_packet()
     * @return String
     * @access private
     */
    function _filter($payload)
    {
        switch (ord($payload[0])) {
            case NET_SSH2_MSG_DISCONNECT:
                $this->_string_shift($payload, 1);
                extract(unpack('Nreason_code/Nlength', $this->_string_shift($payload, 8)));
                $this->errors[] = 'SSH_MSG_DISCONNECT: ' . $this->disconnect_reasons[$reason_code] . "\r\n" . utf8_decode($this->_string_shift($payload, $length));
                $this->bitmask = 0;
                return false;
            case NET_SSH2_MSG_IGNORE:
                $payload = $this->_get_binary_packet();
                break;
            case NET_SSH2_MSG_DEBUG:
                $this->_string_shift($payload, 2);
                extract(unpack('Nlength', $this->_string_shift($payload, 4)));
                $this->errors[] = 'SSH_MSG_DEBUG: ' . utf8_decode($this->_string_shift($payload, $length));
                $payload = $this->_get_binary_packet();
                break;
            case NET_SSH2_MSG_UNIMPLEMENTED:
                return false;
            case NET_SSH2_MSG_KEXINIT:
                if ($this->session_id !== false) {
                    if (!$this->_key_exchange($payload)) {
                        $this->bitmask = 0;
                        return false;
                    }
                    $payload = $this->_get_binary_packet();
                }
        }

        // see http://tools.ietf.org/html/rfc4252#section-5.4; only called when the encryption has been activated and when we haven't already logged in
        if (($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR) && !($this->bitmap & NET_SSH2_MASK_LOGIN) && ord($payload[0]) == NET_SSH2_MSG_USERAUTH_BANNER) {
            $this->_string_shift($payload, 1);
            extract(unpack('Nlength', $this->_string_shift($payload, 4)));
            $this->errors[] = 'SSH_MSG_USERAUTH_BANNER: ' . utf8_decode($this->_string_shift($payload, $length));
            $payload = $this->_get_binary_packet();
        }

        // only called when we've already logged in
        if (($this->bitmap & NET_SSH2_MASK_CONSTRUCTOR) && ($this->bitmap & NET_SSH2_MASK_LOGIN)) {
            switch (ord($payload[0])) {
                case NET_SSH2_MSG_GLOBAL_REQUEST: // see http://tools.ietf.org/html/rfc4254#section-4
                    $this->_string_shift($payload, 1);
                    extract(unpack('Nlength', $this->_string_shift($payload)));
                    $this->errors[] = 'SSH_MSG_GLOBAL_REQUEST: ' . utf8_decode($this->_string_shift($payload, $length));

                    if (!$this->_send_binary_packet(pack('C', NET_SSH2_MSG_REQUEST_FAILURE))) {
                        return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
                    }

                    $payload = $this->_get_binary_packet();
                    break;
                case NET_SSH2_MSG_CHANNEL_OPEN: // see http://tools.ietf.org/html/rfc4254#section-5.1
                    $this->_string_shift($payload, 1);
                    extract(unpack('N', $this->_string_shift($payload, 4)));
                    $this->errors[] = 'SSH_MSG_CHANNEL_OPEN: ' . utf8_decode($this->_string_shift($payload, $length));

                    $this->_string_shift($payload, 4); // skip over client channel
                    extract(unpack('Nserver_channel', $this->_string_shift($payload, 4)));

                    $packet = pack('CN3a*Na*',
                        NET_SSH2_MSG_REQUEST_FAILURE, $server_channel, NET_SSH2_OPEN_ADMINISTRATIVELY_PROHIBITED, 0, '', 0, '');

                    if (!$this->_send_binary_packet($packet)) {
                        return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
                    }

                    $payload = $this->_get_binary_packet();
                    break;
                case NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST:
                    $payload = $this->_get_binary_packet();
            }
        }

        return $payload;
    }

    /**
     * Gets channel data
     *
     * Returns the data as a string if it's available and false if not.
     *
     * @param $client_channel
     * @return Mixed
     * @access private
     */
    function _get_channel_packet($client_channel, $skip_extended = false)
    {
        if (!empty($this->channel_buffers[$client_channel])) {
            return array_shift($this->channel_buffers[$client_channel]);
        }

        while (true) {
            if ($this->curTimeout) {
                $read = array($this->fsock);
                $write = $except = NULL;

                stream_set_blocking($this->fsock, false);

                $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838
                // on windows this returns a "Warning: Invalid CRT parameters detected" error
                if (!@stream_select($read, $write, $except, $this->curTimeout)) {
                    stream_set_blocking($this->fsock, true);
                    $this->_close_channel($client_channel);
                    return true;
                }
                $elapsed = strtok(microtime(), ' ') + strtok('') - $start;
                $this->curTimeout-= $elapsed;

                stream_set_blocking($this->fsock, true);
            }

            $response = $this->_get_binary_packet();
            if ($response === false) {
                user_error('Connection closed by server', E_USER_NOTICE);
                return false;
            }

            if (empty($response)) {
                return '';
            }

            extract(unpack('Ctype/Nchannel', $this->_string_shift($response, 5)));

            switch ($this->channel_status[$channel]) {
                case NET_SSH2_MSG_CHANNEL_OPEN:
                    switch ($type) {
                        case NET_SSH2_MSG_CHANNEL_OPEN_CONFIRMATION:
                            extract(unpack('Nserver_channel', $this->_string_shift($response, 4)));
                            $this->server_channels[$channel] = $server_channel;
                            $this->_string_shift($response, 4); // skip over (server) window size
                            $temp = unpack('Npacket_size_client_to_server', $this->_string_shift($response, 4));
                            $this->packet_size_client_to_server[$channel] = $temp['packet_size_client_to_server'];
                            return $client_channel == $channel ? true : $this->_get_channel_packet($client_channel, $skip_extended);
                        //case NET_SSH2_MSG_CHANNEL_OPEN_FAILURE:
                        default:
                            user_error('Unable to open channel', E_USER_NOTICE);
                            return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
                    }
                    break;
                case NET_SSH2_MSG_CHANNEL_REQUEST:
                    switch ($type) {
                        case NET_SSH2_MSG_CHANNEL_SUCCESS:
                            return true;
                        //case NET_SSH2_MSG_CHANNEL_FAILURE:
                        default:
                            user_error('Unable to request pseudo-terminal', E_USER_NOTICE);
                            return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
                    }
                case NET_SSH2_MSG_CHANNEL_CLOSE:
                    return $type == NET_SSH2_MSG_CHANNEL_CLOSE ? true : $this->_get_channel_packet($client_channel, $skip_extended);
            }

            switch ($type) {
                case NET_SSH2_MSG_CHANNEL_DATA:
                    /*
                    if ($client_channel == NET_SSH2_CHANNEL_EXEC) {
                        // SCP requires null packets, such as this, be sent.  further, in the case of the ssh.com SSH server
                        // this actually seems to make things twice as fast.  more to the point, the message right after 
                        // SSH_MSG_CHANNEL_DATA (usually SSH_MSG_IGNORE) won't block for as long as it would have otherwise.
                        // in OpenSSH it slows things down but only by a couple thousandths of a second.
                        $this->_send_channel_packet($client_channel, chr(0));
                    }
                    */
                    extract(unpack('Nlength', $this->_string_shift($response, 4)));
                    $data = $this->_string_shift($response, $length);
                    if ($client_channel == $channel) {
                        return $data;
                    }
                    if (!isset($this->channel_buffers[$client_channel])) {
                        $this->channel_buffers[$client_channel] = array();
                    }
                    $this->channel_buffers[$client_channel][] = $data;
                    break;
                case NET_SSH2_MSG_CHANNEL_EXTENDED_DATA:
                    if ($skip_extended) {
                        break;
                    }
                    /*
                    if ($client_channel == NET_SSH2_CHANNEL_EXEC) {
                        $this->_send_channel_packet($client_channel, chr(0));
                    }
                    */
                    // currently, there's only one possible value for $data_type_code: NET_SSH2_EXTENDED_DATA_STDERR
                    extract(unpack('Ndata_type_code/Nlength', $this->_string_shift($response, 8)));
                    $data = $this->_string_shift($response, $length);
                    if ($client_channel == $channel) {
                        return $data;
                    }
                    if (!isset($this->channel_buffers[$client_channel])) {
                        $this->channel_buffers[$client_channel] = array();
                    }
                    $this->channel_buffers[$client_channel][] = $data;
                    break;
                case NET_SSH2_MSG_CHANNEL_REQUEST:
                    extract(unpack('Nlength', $this->_string_shift($response, 4)));
                    $value = $this->_string_shift($response, $length);
                    switch ($value) {
                        case 'exit-signal':
                            $this->_string_shift($response, 1);
                            extract(unpack('Nlength', $this->_string_shift($response, 4)));
                            $this->errors[] = 'SSH_MSG_CHANNEL_REQUEST (exit-signal): ' . $this->_string_shift($response, $length);
                            $this->_string_shift($response, 1);
                            extract(unpack('Nlength', $this->_string_shift($response, 4)));
                            if ($length) {
                                $this->errors[count($this->errors)].= "\r\n" . $this->_string_shift($response, $length);
                            }
                        case 'exit-status':
                            // "The channel needs to be closed with SSH_MSG_CHANNEL_CLOSE after this message."
                            // -- http://tools.ietf.org/html/rfc4254#section-6.10
                            $this->_send_binary_packet(pack('CN', NET_SSH2_MSG_CHANNEL_EOF, $this->server_channels[$client_channel]));
                            $this->_send_binary_packet(pack('CN', NET_SSH2_MSG_CHANNEL_CLOSE, $this->server_channels[$channel]));

                            $this->channel_status[$channel] = NET_SSH2_MSG_CHANNEL_EOF;
                        default:
                            // "Some systems may not implement signals, in which case they SHOULD ignore this message."
                            //  -- http://tools.ietf.org/html/rfc4254#section-6.9
                            break;
                    }
                    break;
                case NET_SSH2_MSG_CHANNEL_CLOSE:
                    $this->curTimeout = 0;

                    if ($this->bitmap & NET_SSH2_MASK_SHELL) {
                        $this->bitmap&= ~NET_SSH2_MASK_SHELL;
                    }
                    if ($this->channel_status[$channel] != NET_SSH2_MSG_CHANNEL_EOF) {
                        $this->_send_binary_packet(pack('CN', NET_SSH2_MSG_CHANNEL_CLOSE, $this->server_channels[$channel]));
                    }

                    $this->channel_status[$channel] = NET_SSH2_MSG_CHANNEL_CLOSE;
                    return true;
                case NET_SSH2_MSG_CHANNEL_EOF:
                    break;
                default:
                    user_error('Error reading channel data', E_USER_NOTICE);
                    return $this->_disconnect(NET_SSH2_DISCONNECT_BY_APPLICATION);
            }
        }
    }

    /**
     * Sends Binary Packets
     *
     * See '6. Binary Packet Protocol' of rfc4253 for more info.
     *
     * @param String $data
     * @see Net_SSH2::_get_binary_packet()
     * @return Boolean
     * @access private
     */
    function _send_binary_packet($data)
    {
        if (!is_resource($this->fsock) || feof($this->fsock)) {
            user_error('Connection closed prematurely', E_USER_NOTICE);
            return false;
        }

        //if ($this->compress) {
        //    // the -4 removes the checksum:
        //    // http://php.net/function.gzcompress#57710
        //    $data = substr(gzcompress($data), 0, -4);
        //}

        // 4 (packet length) + 1 (padding length) + 4 (minimal padding amount) == 9
        $packet_length = strlen($data) + 9;
        // round up to the nearest $this->encrypt_block_size
        $packet_length+= (($this->encrypt_block_size - 1) * $packet_length) % $this->encrypt_block_size;
        // subtracting strlen($data) is obvious - subtracting 5 is necessary because of packet_length and padding_length
        $padding_length = $packet_length - strlen($data) - 5;

        $padding = '';
        for ($i = 0; $i < $padding_length; $i++) {
            $padding.= chr(crypt_random(0, 255));
        }

        // we subtract 4 from packet_length because the packet_length field isn't supposed to include itself
        $packet = pack('NCa*', $packet_length - 4, $padding_length, $data . $padding);

        $hmac = $this->hmac_create !== false ? $this->hmac_create->hash(pack('Na*', $this->send_seq_no, $packet)) : '';
        $this->send_seq_no++;

        if ($this->encrypt !== false) {
            $packet = $this->encrypt->encrypt($packet);
        }

        $packet.= $hmac;

        $start = strtok(microtime(), ' ') + strtok(''); // http://php.net/microtime#61838
        $result = strlen($packet) == fputs($this->fsock, $packet);
        $stop = strtok(microtime(), ' ') + strtok('');

        if (defined('NET_SSH2_LOGGING')) {
            $message_number = isset($this->message_numbers[ord($data[0])]) ? $this->message_numbers[ord($data[0])] : 'UNKNOWN (' . ord($data[0]) . ')';
            $message_number = '-> ' . $message_number .
                              ' (' . round($stop - $start, 4) . 's)';
            $this->_append_log($message_number, $data);
        }

        return $result;
    }

    /**
     * Logs data packets
     *
     * Makes sure that only the last 1MB worth of packets will be logged
     *
     * @param String $data
     * @access private
     */
    function _append_log($message_number, $message)
    {
            switch (NET_SSH2_LOGGING) {
                // useful for benchmarks
                case NET_SSH2_LOG_SIMPLE:
                    $this->message_number_log[] = $message_number;
                    break;
                // the most useful log for SSH2
                case NET_SSH2_LOG_COMPLEX:
                    $this->message_number_log[] = $message_number;
                    $this->_string_shift($message);
                    $this->log_size+= strlen($message);
                    $this->message_log[] = $message;
                    while ($this->log_size > NET_SSH2_LOG_MAX_SIZE) {
                        $this->log_size-= strlen(array_shift($this->message_log));
                        array_shift($this->message_number_log);
                    }
                    break;
                // dump the output out realtime; packets may be interspersed with non packets,
                // passwords won't be filtered out and select other packets may not be correctly
                // identified
                case NET_SSH2_LOG_REALTIME:
                    echo "<pre>\r\n" . $this->_format_log(array($message), array($message_number)) . "\r\n</pre>\r\n";
                    flush();
                    ob_flush();
                    break;
                // basically the same thing as NET_SSH2_LOG_REALTIME with the caveat that NET_SSH2_LOG_REALTIME_FILE
                // needs to be defined and that the resultant log file will be capped out at NET_SSH2_LOG_MAX_SIZE. 
                // the earliest part of the log file is denoted by the first <<< START >>> and is not going to necessarily
                // at the beginning of the file
                case NET_SSH2_LOG_REALTIME_FILE:
                    if (!isset($this->realtime_log_file)) {
                        // PHP doesn't seem to like using constants in fopen()
                        $filename = NET_SSH2_LOG_REALTIME_FILE;
                        $fp = fopen($filename, 'w');
                        $this->realtime_log_file = $fp;
                    }
                    if (!is_resource($this->realtime_log_file)) {
                        break;
                    }
                    $entry = $this->_format_log(array($message), array($message_number));
                    if ($this->realtime_log_wrap) {
                        $temp = "<<< START >>>\r\n";
                        $entry.= $temp;
                        fseek($this->realtime_log_file, ftell($this->realtime_log_file) - strlen($temp));
                    }
                    $this->realtime_log_size+= strlen($entry);
                    if ($this->realtime_log_size > NET_SSH2_LOG_MAX_SIZE) {
                        fseek($this->realtime_log_file, 0);
                        $this->realtime_log_size = strlen($entry);
                        $this->realtime_log_wrap = true;
                    }
                    fputs($this->realtime_log_file, $entry);
            }
    }

    /**
     * Sends channel data
     *
     * Spans multiple SSH_MSG_CHANNEL_DATAs if appropriate
     *
     * @param Integer $client_channel
     * @param String $data
     * @return Boolean
     * @access private
     */
    function _send_channel_packet($client_channel, $data)
    {
        while (strlen($data) > $this->packet_size_client_to_server[$client_channel]) {
            // resize the window, if appropriate
            $this->window_size_client_to_server[$client_channel]-= $this->packet_size_client_to_server[$client_channel];
            if ($this->window_size_client_to_server[$client_channel] < 0) {
                $packet = pack('CNN', NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST, $this->server_channels[$client_channel], $this->window_size);
                if (!$this->_send_binary_packet($packet)) {
                    return false;
                }
                $this->window_size_client_to_server[$client_channel]+= $this->window_size;
            }

            $packet = pack('CN2a*',
                NET_SSH2_MSG_CHANNEL_DATA,
                $this->server_channels[$client_channel],
                $this->packet_size_client_to_server[$client_channel],
                $this->_string_shift($data, $this->packet_size_client_to_server[$client_channel])
            );

            if (!$this->_send_binary_packet($packet)) {
                return false;
            }
        }

        // resize the window, if appropriate
        $this->window_size_client_to_server[$client_channel]-= strlen($data);
        if ($this->window_size_client_to_server[$client_channel] < 0) {
            $packet = pack('CNN', NET_SSH2_MSG_CHANNEL_WINDOW_ADJUST, $this->server_channels[$client_channel], $this->window_size);
            if (!$this->_send_binary_packet($packet)) {
                return false;
            }
            $this->window_size_client_to_server[$client_channel]+= $this->window_size;
        }

        return $this->_send_binary_packet(pack('CN2a*',
            NET_SSH2_MSG_CHANNEL_DATA,
            $this->server_channels[$client_channel],
            strlen($data),
            $data));
    }

    /**
     * Closes and flushes a channel
     *
     * Net_SSH2 doesn't properly close most channels.  For exec() channels are normally closed by the server
     * and for SFTP channels are presumably closed when the client disconnects.  This functions is intended
     * for SCP more than anything.
     *
     * @param Integer $client_channel
     * @return Boolean
     * @access private
     */
    function _close_channel($client_channel)
    {
        // see http://tools.ietf.org/html/rfc4254#section-5.3

        $this->_send_binary_packet(pack('CN', NET_SSH2_MSG_CHANNEL_EOF, $this->server_channels[$client_channel]));

        $this->_send_binary_packet(pack('CN', NET_SSH2_MSG_CHANNEL_CLOSE, $this->server_channels[$client_channel]));

        $this->channel_status[$client_channel] = NET_SSH2_MSG_CHANNEL_CLOSE;

        $this->curTimeout = 0;

        while (!is_bool($this->_get_channel_packet($client_channel)));

        if ($this->bitmap & NET_SSH2_MASK_SHELL) {
            $this->bitmap&= ~NET_SSH2_MASK_SHELL;
        }
    }

    /**
     * Disconnect
     *
     * @param Integer $reason
     * @return Boolean
     * @access private
     */
    function _disconnect($reason)
    {
        if ($this->bitmap) {
            $data = pack('CNNa*Na*', NET_SSH2_MSG_DISCONNECT, $reason, 0, '', 0, '');
            $this->_send_binary_packet($data);
            $this->bitmap = 0;
            fclose($this->fsock);
            return false;
        }
    }

    /**
     * String Shift
     *
     * Inspired by array_shift
     *
     * @param String $string
     * @param optional Integer $index
     * @return String
     * @access private
     */
    function _string_shift(&$string, $index = 1)
    {
        $substr = substr($string, 0, $index);
        $string = substr($string, $index);
        return $substr;
    }

    /**
     * Define Array
     *
     * Takes any number of arrays whose indices are integers and whose values are strings and defines a bunch of
     * named constants from it, using the value as the name of the constant and the index as the value of the constant.
     * If any of the constants that would be defined already exists, none of the constants will be defined.
     *
     * @param Array $array
     * @access private
     */
    function _define_array()
    {
        $args = func_get_args();
        foreach ($args as $arg) {
            foreach ($arg as $key=>$value) {
                if (!defined($value)) {
                    define($value, $key);
                } else {
                    break 2;
                }
            }
        }
    }

    /**
     * Returns a log of the packets that have been sent and received.
     *
     * Returns a string if NET_SSH2_LOGGING == NET_SSH2_LOG_COMPLEX, an array if NET_SSH2_LOGGING == NET_SSH2_LOG_SIMPLE and false if !defined('NET_SSH2_LOGGING')
     *
     * @access public
     * @return String or Array
     */
    function getLog()
    {
        if (!defined('NET_SSH2_LOGGING')) {
            return false;
        }

        switch (NET_SSH2_LOGGING) {
            case NET_SSH2_LOG_SIMPLE:
                return $this->message_number_log;
                break;
            case NET_SSH2_LOG_COMPLEX:
                return $this->_format_log($this->message_log, $this->message_number_log);
                break;
            default:
                return false;
        }
    }

    /**
     * Formats a log for printing
     *
     * @param Array $message_log
     * @param Array $message_number_log
     * @access private
     * @return String
     */
    function _format_log($message_log, $message_number_log)
    {
        static $boundary = ':', $long_width = 65, $short_width = 16;

        $output = '';
        for ($i = 0; $i < count($message_log); $i++) {
            $output.= $message_number_log[$i] . "\r\n";
            $current_log = $message_log[$i];
            $j = 0;
            do {
                if (!empty($current_log)) {
                    $output.= str_pad(dechex($j), 7, '0', STR_PAD_LEFT) . '0  ';
                }
                $fragment = $this->_string_shift($current_log, $short_width);
                $hex = substr(
                           preg_replace(
                               '#(.)#es',
                               '"' . $boundary . '" . str_pad(dechex(ord(substr("\\1", -1))), 2, "0", STR_PAD_LEFT)',
                               $fragment),
                           strlen($boundary)
                       );
                // replace non ASCII printable characters with dots
                // http://en.wikipedia.org/wiki/ASCII#ASCII_printable_characters
                // also replace < with a . since < messes up the output on web browsers
                $raw = preg_replace('#[^\x20-\x7E]|<#', '.', $fragment);
                $output.= str_pad($hex, $long_width - $short_width, ' ') . $raw . "\r\n";
                $j++;
            } while (!empty($current_log));
            $output.= "\r\n";
        }

        return $output;
    }

    /**
     * Returns all errors
     *
     * @return String
     * @access public
     */
    function getErrors()
    {
        return $this->errors;
    }

    /**
     * Returns the last error
     *
     * @return String
     * @access public
     */
    function getLastError()
    {
        return $this->errors[count($this->errors) - 1];
    }

    /**
     * Return the server identification.
     *
     * @return String
     * @access public
     */
    function getServerIdentification()
    {
        return $this->server_identifier;
    }

    /**
     * Return a list of the key exchange algorithms the server supports.
     *
     * @return Array
     * @access public
     */
    function getKexAlgorithms()
    {
        return $this->kex_algorithms;
    }

    /**
     * Return a list of the host key (public key) algorithms the server supports.
     *
     * @return Array
     * @access public
     */
    function getServerHostKeyAlgorithms()
    {
        return $this->server_host_key_algorithms;
    }

    /**
     * Return a list of the (symmetric key) encryption algorithms the server supports, when receiving stuff from the client.
     *
     * @return Array
     * @access public
     */
    function getEncryptionAlgorithmsClient2Server()
    {
        return $this->encryption_algorithms_client_to_server;
    }

    /**
     * Return a list of the (symmetric key) encryption algorithms the server supports, when sending stuff to the client.
     *
     * @return Array
     * @access public
     */
    function getEncryptionAlgorithmsServer2Client()
    {
        return $this->encryption_algorithms_server_to_client;
    }

    /**
     * Return a list of the MAC algorithms the server supports, when receiving stuff from the client.
     *
     * @return Array
     * @access public
     */
    function getMACAlgorithmsClient2Server()
    {
        return $this->mac_algorithms_client_to_server;
    }

    /**
     * Return a list of the MAC algorithms the server supports, when sending stuff to the client.
     *
     * @return Array
     * @access public
     */
    function getMACAlgorithmsServer2Client()
    {
        return $this->mac_algorithms_server_to_client;
    }

    /**
     * Return a list of the compression algorithms the server supports, when receiving stuff from the client.
     *
     * @return Array
     * @access public
     */
    function getCompressionAlgorithmsClient2Server()
    {
        return $this->compression_algorithms_client_to_server;
    }

    /**
     * Return a list of the compression algorithms the server supports, when sending stuff to the client.
     *
     * @return Array
     * @access public
     */
    function getCompressionAlgorithmsServer2Client()
    {
        return $this->compression_algorithms_server_to_client;
    }

    /**
     * Return a list of the languages the server supports, when sending stuff to the client.
     *
     * @return Array
     * @access public
     */
    function getLanguagesServer2Client()
    {
        return $this->languages_server_to_client;
    }

    /**
     * Return a list of the languages the server supports, when receiving stuff from the client.
     *
     * @return Array
     * @access public
     */
    function getLanguagesClient2Server()
    {
        return $this->languages_client_to_server;
    }

    /**
     * Returns the server public host key.
     *
     * Caching this the first time you connect to a server and checking the result on subsequent connections
     * is recommended.  Returns false if the server signature is not signed correctly with the public host key.
     *
     * @return Mixed
     * @access public
     */
    function getServerPublicHostKey()
    {
        $signature = $this->signature;
        $server_public_host_key = $this->server_public_host_key;

        extract(unpack('Nlength', $this->_string_shift($server_public_host_key, 4)));
        $this->_string_shift($server_public_host_key, $length);

        if ($this->signature_validated) {
            return $this->bitmap ?
                $this->signature_format . ' ' . base64_encode($this->server_public_host_key) :
                false;
        }

        $this->signature_validated = true;

        switch ($this->signature_format) {
            case 'ssh-dss':
                $temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
                $p = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);

                $temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
                $q = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);

                $temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
                $g = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);

                $temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
                $y = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);

                /* The value for 'dss_signature_blob' is encoded as a string containing
                   r, followed by s (which are 160-bit integers, without lengths or
                   padding, unsigned, and in network byte order). */
                $temp = unpack('Nlength', $this->_string_shift($signature, 4));
                if ($temp['length'] != 40) {
                    user_error('Invalid signature', E_USER_NOTICE);
                    return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
                }

                $r = new Math_BigInteger($this->_string_shift($signature, 20), 256);
                $s = new Math_BigInteger($this->_string_shift($signature, 20), 256);

                if ($r->compare($q) >= 0 || $s->compare($q) >= 0) {
                    user_error('Invalid signature', E_USER_NOTICE);
                    return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
                }

                $w = $s->modInverse($q);

                $u1 = $w->multiply(new Math_BigInteger(sha1($this->exchange_hash), 16));
                list(, $u1) = $u1->divide($q);

                $u2 = $w->multiply($r);
                list(, $u2) = $u2->divide($q);

                $g = $g->modPow($u1, $p);
                $y = $y->modPow($u2, $p);

                $v = $g->multiply($y);
                list(, $v) = $v->divide($p);
                list(, $v) = $v->divide($q);

                if (!$v->equals($r)) {
                    user_error('Bad server signature', E_USER_NOTICE);
                    return $this->_disconnect(NET_SSH2_DISCONNECT_HOST_KEY_NOT_VERIFIABLE);
                }

                break;
            case 'ssh-rsa':
                $temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
                $e = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);

                $temp = unpack('Nlength', $this->_string_shift($server_public_host_key, 4));
                $n = new Math_BigInteger($this->_string_shift($server_public_host_key, $temp['length']), -256);
                $nLength = $temp['length'];

                /*
                $temp = unpack('Nlength', $this->_string_shift($signature, 4));
                $signature = $this->_string_shift($signature, $temp['length']);

                if (!class_exists('Crypt_RSA')) {
                    require_once('Crypt/RSA.php');
                }

                $rsa = new Crypt_RSA();
                $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
                $rsa->loadKey(array('e' => $e, 'n' => $n), CRYPT_RSA_PUBLIC_FORMAT_RAW);
                if (!$rsa->verify($this->exchange_hash, $signature)) {
                    user_error('Bad server signature', E_USER_NOTICE);
                    return $this->_disconnect(NET_SSH2_DISCONNECT_HOST_KEY_NOT_VERIFIABLE);
                }
                */

                $temp = unpack('Nlength', $this->_string_shift($signature, 4));
                $s = new Math_BigInteger($this->_string_shift($signature, $temp['length']), 256);

                // validate an RSA signature per "8.2 RSASSA-PKCS1-v1_5", "5.2.2 RSAVP1", and "9.1 EMSA-PSS" in the
                // following URL:
                // ftp://ftp.rsasecurity.com/pub/pkcs/pkcs-1/pkcs-1v2-1.pdf

                // also, see SSHRSA.c (rsa2_verifysig) in PuTTy's source.

                if ($s->compare(new Math_BigInteger()) < 0 || $s->compare($n->subtract(new Math_BigInteger(1))) > 0) {
                    user_error('Invalid signature', E_USER_NOTICE);
                    return $this->_disconnect(NET_SSH2_DISCONNECT_KEY_EXCHANGE_FAILED);
                }

                $s = $s->modPow($e, $n);
                $s = $s->toBytes();

                $h = pack('N4H*', 0x00302130, 0x0906052B, 0x0E03021A, 0x05000414, sha1($this->exchange_hash));
                $h = chr(0x01) . str_repeat(chr(0xFF), $nLength - 3 - strlen($h)) . $h;

                if ($s != $h) {
                    user_error('Bad server signature', E_USER_NOTICE);
                    return $this->_disconnect(NET_SSH2_DISCONNECT_HOST_KEY_NOT_VERIFIABLE);
                }
                break;
            default:
                user_error('Unsupported signature format', E_USER_NOTICE);
                return $this->_disconnect(NET_SSH2_DISCONNECT_HOST_KEY_NOT_VERIFIABLE);
        }

        return $this->signature_format . ' ' . base64_encode($this->server_public_host_key);
    }
}
