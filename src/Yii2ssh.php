<?php
namespace ourren\yii2ssh;

use Yii;
use phpseclib\Net\SSH2;
use phpseclib\Crypt\RSA;
use yii\base\Widget;

class LoginFailedException extends \Exception {}
class LoginUnknownException extends \Exception {}
class NotConnectedException extends \Exception {}

class Yii2ssh extends Widget
{
	/**
	 * Store of the ssh session.
	 *
	 * @var Yii2ssh
	 */
	private $ssh = null;

	/***
	 * Connect to the ssh server.
	 *
	 * @param string $host
	 * @param array $auth
	 * 	 * Login via username/password
	 *     [
	 *         'username' => 'myname',
	 *         'password' => 'mypassword', // can be empty
	 *      ]
	 * @param integer $port Default 22
	 * @param integer $timeout Default 10 seconds
	 * @return bool
	 */
	public function connect($host, $auth, $port = 22, $timeout = 10)
	{
		$ret = false;
		$this->ssh = new SSH2($host, $port, $timeout);

		if (!isset($auth['key']) && isset($auth['username'])) {
			// Login via username/password

			$username = $auth['username'];
			$password = isset($auth['password']) ? $auth['password'] : '';

			if ($this->ssh->login($username, $password))
			{
				$ret = true;
			}
		}
		return $ret;
	}

	/**
	 * Read the next line from the SSH session.
	 *
	 * @return string|null
	 */
	public function readLine()
	{
		$output = $this->ssh->_get_channel_packet(SSH2::CHANNEL_EXEC);

		return $output === true ? null : $output;
	}

	/**
	 * Run a ssh command for the current connection.
	 *
	 * @param string|array $commands
	 * @param callable $callback
	 *
	 * @throws NotConnectedException If the client is not connected to the server
	 *
	 * @return string|null
	 */
	public function runCommands($commands, $callback = null)
	{
		if (!$this->ssh->isConnected())
			throw new NotConnectedException();

		if (is_array($commands))
			$commands = implode(' && ', $commands);

		if ($callback === null)
			$output = '';

		$this->ssh->exec($commands, false);

		while (true) {
			if (is_null($line = $this->readLine())) break;

			if ($callback === null)
				$output .= $line;
			else
				call_user_func($callback, $line, $this);
		}

		if ($callback === null)
			return $output;
		else
			return null;
	}

	/**
	 * Returns the log messages of the connection.
	 *
	 * @return array
	 */
	public function getLog()
	{
		return $this->ssh->getLog();
	}
}
