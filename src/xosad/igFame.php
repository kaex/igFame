<?php

namespace xosad;

class igFame extends Common
{
	public  $user_agent      = 'Instagram 10.33.0 (iPad2,1; iPhone OS 9_3_5; en_RO; en-RO; scale=2.00; gamut=normal; 640x960) AppleWebKit/420+';
	public  $apiUrl          = 'https://i.instagram.com/api/v1';
	private $ig_sig_key      = 'f612a5bc5effcb371d0d8b89c9c65be8efc6b1c49e947f6c8d7be29d5587cb5f';
	private $sig_key_version = '5';
	private $like_depth_per_user;
	private $like_depth_per_tag;
	private $sleep;
	private $username;
	private $password;
	private $tags;
	private $blacklisted_tags;
	private $blacklisted_usernames;
	private $mid;
	private $device_id;
	private $proxy;
	private $csrftoken;
	private $headers         = [
		'Host: i.instagram.com',
		'Accept-Language: en-RO;q=1',
		'X-IG-Connection-Type: WiFi',
		'X-IG-Capabilities: 36oH',
		'X-IG-Connection-Speed:	1432kbps',
		'Accept: */*',
		'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
		'Connection: keep-alive',
		'Proxy-Connection: keep-alive',
		'Accept-Encoding: gzip, deflate',
	];

	/**
	 * igFame constructor.
	 *
	 * Uses config.json to generate needed data.
	 */
	public function __construct()
	{
		if (!file_exists(ROOTDIR . '/config.json'))
		{
			$this->echoColored('config.json is empty.', 'black', 'red', true);
		}

		$this->device_id = $this->generateUUID();

		$config = json_decode(file_get_contents(ROOTDIR . '/config.json'), true);

		$this->username              = $config['account']['username'];
		$this->password              = $config['account']['password'];
		$this->sleep                 = $config['sleep_delay'];
		$this->like_depth_per_user   = $config['like_depth_per_user'];
		$this->like_depth_per_tag    = $config['like_depth_per_tag'];
		$this->tags                  = $config['tags'];
		$this->blacklisted_tags      = $config['blacklisted_tags'];
		$this->blacklisted_usernames = $config['blacklisted_usernames'];
		$this->proxy                 = $config['proxy'];

		if (empty($this->tags))
		{
			$this->echoColored('tags are empty.', 'black', 'red', true);
		}
	}

	/**
	 * Close curl connection.
	 */
	public function __destruct()
	{
		self::destruct();
	}

	/**
	 * This double checking happenes.
	 * @throws \Exception
	 */
	public function startCore(): void
	{
		if (!file_exists(ROOTDIR . '/cookies/' . $this->username . '.txt'))
		{
			$this->login();
		}
		else
		{
			$json = json_decode(file_get_contents(ROOTDIR . '/user_data/' . $this->username . '.json'), true);

			if (!empty($json['wait_until']) && strtotime(str_replace('/', '-', $json['wait_until'])) > time())
			{
				$this->echoColored('please wait until: ' . $json['wait_until'] . ' to start or you might get banned', 'black', 'red', true);
			}

			$this->checkCookie();
			$json['bot_started'] = date('d/m/Y H:i');
			file_put_contents(ROOTDIR . '/user_data/' . $this->username . '.json', json_encode($json));
			$this->start();
		}
	}

	/**
	 * This is where the magic happenes.
	 * @throws \Exception
	 */
	private function start(): void
	{
		while (1)
		{
			shuffle($this->tags);
			foreach ($this->tags as $tag)
			{
				$i = 1;
				$this->echoColored('-- fetching posts from tag: ' . $tag . ' --', 'cyan', '', false);
				foreach ($this->getTagPosts($tag)['items'] as $post)
				{
					if ($this->like_depth_per_tag === $i)
					{
						$this->echoColored('liked ' . $this->like_depth_per_tag . ' posts from ' . $tag, 'cyan', '', false);
						break;
					}

					if ($this->isBlacklisted($post['code'], 'tag', $post['caption']['text']) || $this->isBlacklisted('', 'username', $post['user']['username']))
					{
						continue;
					}

					$this->likePost($tag, $post['id']);

					$this->echoColored('(' . $i . ') liked post: https://www.instagram.com/p/' . $post['code'] . '/ owner: @' . $post['user']['username'] . ' from tag: ' . $tag, 'green', '', false);

					$userMedias = $this->getUserMedia($post['user']['pk'])['items'];

					if (is_array($userMedias))
					{
						$this->echoColored('fetched @' . $post['user']['username'] . ' medias will like random ' . $this->like_depth_per_user . ' images', 'yellow', '', false);

						$user_i = 1;
						foreach (array_rand($userMedias, $this->like_depth_per_user) as $key)
						{
							if ($this->isBlacklisted($userMedias[$key]['code'], 'tag', $userMedias[$key]['caption']['text']) || $this->isBlacklisted('', 'username', $post['user']['username']))
							{
								continue;
							}

							$this->likePost($tag, $userMedias[$key]['id']);

							$this->echoColored('(' . $user_i . ') liked @' . $post['user']['username'] . ' post: https://www.instagram.com/p/' . $userMedias[$key]['code'] . '/', 'blue', '', false);

							$sleepShort = random_int(10, 20);
							echo 'sleeping ' . $sleepShort . ' seconds' . PHP_EOL;
							sleep($sleepShort);

							$user_i++;
						}
					}
					else
					{
						$this->echoColored('could not fetch @' . $post['user']['username'] . 'posts, moving to next', 'red', '', false);
					}

					$i++;
				}

				$sleep = random_int(30, 80);

				$this->echoColored('sleeping before next tag ' . $sleep . ' seconds', 'yellow', '', false);
				sleep($sleep);
			}
			$this->echoColored('finished all given tags sleeping ' . $this->sleep . ' seconds', 'yellow', '', false);
			sleep($this->sleep);
		}
	}

	/**
	 * Check's if cookie is alive if not sends back to login()
	 * @throws \Exception
	 */
	private function checkCookie(): ?bool
	{
		self::construct([
			CURLOPT_USERAGENT  => $this->user_agent,
			CURLOPT_HTTPHEADER => $this->headers,
			CURLOPT_COOKIEFILE => ROOTDIR . '/cookies/' . $this->username . '.txt',
			CURLOPT_COOKIEJAR  => ROOTDIR . '/cookies/' . $this->username . '.txt',
		]);

		$result = self::request($this->apiUrl . '/feed/reels_tray/', '', false, '', $this->proxy);

		$json = json_decode($result, true);

		if ($json['status'] !== 'ok')
		{
			$this->login();
		}

		return true;
	}

	/**
	 * Generates a new instagram _mid token.
	 *
	 * @return string
	 */
	private function generateClientId(): string
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://www.instagram.com/web/__mid/');
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		curl_close($ch);

		$this->mid = $result;

		return $this->mid;
	}

	/**
	 * Generates a new instagram CSRF token.
	 *
	 * @return string
	 */
	private function generateCsrfToken(): string
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://www.instagram.com/data/shared_data/?__a=1');
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		curl_close($ch);

		$json = json_decode($result, true);

		$this->csrftoken = $json['config']['csrf_token'];

		return $json['config']['csrf_token'];
	}

	/**
	 * Logins to instagram.
	 * @return bool
	 * @throws \Exception
	 */
	private function login(): ?bool
	{
		$this->headers [] = 'Cookie: csrftoken=' . $this->generateCsrfToken() . '; mid=' . $this->generateClientId() . '; rur=FTW';

		self::construct([
			CURLOPT_USERAGENT  => $this->user_agent,
			CURLOPT_HTTPHEADER => $this->headers,
			CURLOPT_COOKIEJAR  => ROOTDIR . '/cookies/' . $this->username . '.txt',
		]);

		$params = [
			'login_attempt_count' => 1,
			'password'            => $this->password,
			'_uid'                => $this->generateUUID(),
			'device_id'           => $this->device_id,
			'_csrftoken'          => $this->csrftoken,
			'_uuid'               => $this->device_id,
			'adid'                => $this->generateUUID(),
			'username'            => $this->username,
		];

		$result = self::request($this->apiUrl . '/accounts/login/', $this->generateSignature(json_encode($params)), true, '', $this->proxy);

		$json = json_decode($result, true);

		if ($json['logged_in_user'])
		{
			$array = [
				'account'     => [
					'pk'              => $json['logged_in_user']['pk'],
					'username'        => $this->username,
					'full_name'       => $json['logged_in_user']['full_name'],
					'profile_pic_url' => $json['logged_in_user']['profile_pic_url'],
					'is_private'      => $json['logged_in_user']['is_private'],
				],
				'like_count'  => 0,
				'last_used'   => date('d/m/Y H:i'),
				'bot_started' => date('d/m/Y H:i'),
				'wait_until'  => ''
			];

			file_put_contents(ROOTDIR . '/user_data/' . $this->username . '.json', json_encode($array));

			$this->startCore();
		}
		$this->echoColored($result, 'black', 'red', false);
		die();
	}

	/**
	 * Returns an array of posts inside the specific tag.
	 *
	 * @param string $tag
	 *
	 * @return array
	 */
	public function getTagPosts(string $tag): array
	{
		self::construct([
			CURLOPT_USERAGENT  => $this->user_agent,
			CURLOPT_HTTPHEADER => $this->headers,
			CURLOPT_COOKIEFILE => ROOTDIR . '/cookies/' . $this->username . '.txt',
			CURLOPT_COOKIEJAR  => ROOTDIR . '/cookies/' . $this->username . '.txt',
		]);

		$result = self::request($this->apiUrl . '/feed/tag/' . $tag . '/', '', false, '', $this->proxy);

		return json_decode($result, true);
	}

	/**
	 * Likes the post with the given media_id
	 *
	 * @param string $hashtag
	 * @param string $media_id
	 *
	 * @return bool|null
	 * @throws \Exception
	 */
	public function likePost(string $hashtag, string $media_id): ?bool
	{
		$data = json_decode(file_get_contents(ROOTDIR . '/user_data/' . $this->username . '.json'), true);

		if ($data)
		{
			if ($data['like_count'] >= 300)
			{
				$user_data = json_decode(file_get_contents(ROOTDIR . '/user_data/' . $this->username . '.json'), true);

				$timeDifference = (new \DateTime($user_data['last_used']))->diff((new \DateTime($user_data['bot_started'])));

				if ($timeDifference->h >= 1)
				{
					$this->echoColored('~350 likes limit per hour and you are at 300 likes, killing the bot. please start after: ' . date('d/m/Y H:i', (strtotime('11/12/2019 15:15') + 60 * 60)), 'black', 'red', false);

					$json = json_decode(file_get_contents(ROOTDIR . '/user_data/' . $this->username . '.json'), true);

					$json['wait_until'] = date('d/m/Y H:i', (strtotime('11/12/2019 15:15') + 60 * 60));

					file_put_contents(ROOTDIR . '/user_data/' . $this->username . '.json', json_encode($json));
					die;
				}
			}
			else if ($data['like_count'] <= 300)
			{
				$user_data      = json_decode(file_get_contents(ROOTDIR . '/user_data/' . $this->username . '.json'), true);
				$timeDifference = (new \DateTime($user_data['last_used']))->diff((new \DateTime($user_data['bot_started'])));
				if ($timeDifference->h >= 1)
				{
					$json = json_decode(file_get_contents(ROOTDIR . '/user_data/' . $this->username . '.json'), true);

					$json['like_count'] = 0;

					file_put_contents(ROOTDIR . '/user_data/' . $this->username . '.json', json_encode($json));
				}
			}
		}

		self::construct([
			CURLOPT_USERAGENT  => $this->user_agent,
			CURLOPT_HTTPHEADER => $this->headers,
			CURLOPT_COOKIEFILE => ROOTDIR . '/cookies/' . $this->username . '.txt',
			CURLOPT_COOKIEJAR  => ROOTDIR . '/cookies/' . $this->username . '.txt',
		]);

		$params = [
			'hastag'      => $hashtag,
			'module_name' => 'feed_contextual_hashtag',
			'_uid'        => $this->generateUUID(),
			'_uuid'       => $this->device_id,
			'media_id'    => $media_id,
			'_csrftoken'  => $this->csrftoken,
		];

		$result = self::request($this->apiUrl . '/media/' . $media_id . '/like/?d=0', $this->generateSignature(json_encode($params)), true, $this->proxy);

		$json = json_decode($result, true);

		if ($json)
		{
			if (($json['status'] !== 'ok'))
			{
				$this->echoColored($result, 'black', 'red', false);
				die();
			}

			$json = json_decode(file_get_contents(ROOTDIR . '/user_data/' . $this->username . '.json'), true);

			$json['like_count'] += 1;
			$json['last_used']  = date('d/m/Y H:i');

			file_put_contents(ROOTDIR . '/user_data/' . $this->username . '.json', json_encode($json));
		}
		else
		{
			$this->echoColored('there was an error liking the image.', 'black', 'red', false);
		}

		return true;
	}

	/**
	 * Get users medias/posts
	 *
	 * @param int $id
	 *
	 * @return array
	 */
	public function getUserMedia(int $id): array
	{
		self::construct([
			CURLOPT_USERAGENT  => $this->user_agent,
			CURLOPT_HTTPHEADER => $this->headers,
			CURLOPT_COOKIEFILE => ROOTDIR . '/cookies/' . $this->username . '.txt',
			CURLOPT_COOKIEJAR  => ROOTDIR . '/cookies/' . $this->username . '.txt',
		]);

		$result = self::request($this->apiUrl . '/feed/user/' . $id . '/', '', false, $this->proxy);

		return json_decode($result, true);
	}

	/**
	 * Checks if given text has blacklisted tag.
	 *
	 * @param string $text
	 *
	 * @param string $shortcode
	 *
	 * @param string $type
	 *
	 * @return bool
	 */
	public function isBlacklisted(string $shortcode, string $type, string $text = ''): bool
	{
		if (empty($text))
		{
			return false;
		}

		switch ($type)
		{
			case 'tag':
				preg_match_all('/(?:#(?<hashtag>[^#\r\n]+))/m', $text, $tagsMatches, PREG_SET_ORDER, 0);
				$tags = array_map('trim', array_column($tagsMatches, 'hashtag'));

				if (array_intersect($tags, $this->blacklisted_tags))
				{
					$this->echoColored('blacklisted tag found skipping post: ' . $shortcode . ' found tag/s: ' . implode(',', array_intersect($tags, $this->blacklisted_tags)), 'red', '', false);

					return true;
				}

				return false;
				break;
			case 'username':
				if (array_intersect([$text], $this->blacklisted_usernames))
				{
					$this->echoColored('blacklisted username found skipping post: ' . $shortcode, 'red', '', false);

					return true;
				}

				return false;
				break;
		}

		return false;
	}

	/**
	 * Generates the request postfield with instagram's signature.
	 *
	 * @param string $data
	 *
	 * @return string
	 */
	private function generateSignature(string $data): string
	{
		$hash = hash_hmac('sha256', $data, $this->ig_sig_key);

		return 'ig_sig_key_version=' . $this->sig_key_version . '&signed_body=' . $hash . '.' . urlencode($data);
	}

	/**
	 * Generates a random UUID
	 *
	 * @return string
	 */
	private function generateUUID(): string
	{
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
	}

	/**
	 * Echo CLI with colors
	 *
	 * @param string $text
	 * @param string $fg
	 * @param string $bg
	 * @param bool   $die
	 */
	private function echoColored(string $text, string $fg, string $bg, bool $die = false): void
	{
		echo (new Colors())->getColoredString($text, $fg, $bg) . PHP_EOL;

		if ($die === true)
		{
			die;
		}
	}
}