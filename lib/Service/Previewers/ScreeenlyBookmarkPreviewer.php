<?php
/*
 * Copyright (c) 2020. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

namespace OCA\Bookmarks\Service\Previewers;

use Exception;
use OCA\Bookmarks\Contract\IBookmarkPreviewer;
use OCA\Bookmarks\Contract\IImage;
use OCA\Bookmarks\Db\Bookmark;
use OCA\Bookmarks\Image;
use OCA\Bookmarks\Service\FileCache;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class ScreeenlyBookmarkPreviewer implements IBookmarkPreviewer {
	public const CACHE_PREFIX = 'bookmarks.ScreenlyPreviewService';

	public const HTTP_TIMEOUT = 10 * 1000;

	private $apiKey;

	private $client;

	/** @var IConfig */
	private $config;

	private $cache;

	/** @var LoggerInterface */
	private $logger;

	private $width = 800;

	private $height = 800;
	/**
	 * @var string
	 */
	private $apiUrl;

	public function __construct(FileCache $cache, IConfig $config, IClientService $clientService, LoggerInterface $logger) {
		$this->config = $config;
		$this->apiUrl = $config->getAppValue('bookmarks', 'previews.screenly.url', 'http://screeenly.com/api/v1/fullsize');
		$this->apiKey = $config->getAppValue('bookmarks', 'previews.screenly.token', '');
		$this->client = $clientService->newClient();
		$this->logger = $logger;
	}

	/**
	 * @param Bookmark $bookmark
	 * @return IImage|null
	 */
	public function getImage($bookmark): ?IImage {
		if (!isset($bookmark)) {
			return null;
		}
		if ('' === $this->apiKey) {
			return null;
		}
		$url = $bookmark->getUrl();

		// Fetch image from remote server
		return $this->fetchImage($url);
	}

	/**
	 * @param $url
	 * @return Image|null
	 */
	public function fetchImage($url): ?Image {
		try {
			$response = $this->client->post($this->apiUrl, ['body' => [
				'key' => $this->apiKey,
				'url' => $url,
				'width' => $this->width,
				'height' => $this->height,
			],
				'timeout' => self::HTTP_TIMEOUT,
			]);
			$body = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
		} catch (Exception $e) {
			$this->logger->warning($e->getMessage(), ['app' => 'bookmarks']);
			return null;
		}

		// Some HTPP Error occured :/
		if (200 !== $response->getStatusCode()) {
			return null;
		}

		return new Image('image/jpeg', base64_decode($body['base64_raw']));
	}
}
