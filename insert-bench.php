<?php

$config = array(
	'method' => 'bulk_insert',
	'insertCounts' => array(
		0, 1, 2, 3, 4, 5, 6, 7, 8, 9,
		10,
		50,
		100,
		500,
		1000,
		2500,
		5000,
		7500,
		10000,
		25000,
		50000,
		100000,
		250000,
		500000,
		750000,
		1000000,
	)
);

$db = new CouchDb();
foreach ($config['insertCounts'] as $docCount) {
	// Re-create the database for each attempt
	$db->send('delete', '/benchmark_db');
	$db->send('put', '/benchmark_db');
	//I am add this alias, because in sprintf method, param $method was undefined. I do not know, did should not be done this way ?
	$method = $config['method'];

	echo sprintf("-> %s %d docs:\n", $method, $docCount);

	switch ($config['method']) {
		case 'bulk_insert':
			$insertStart = microtime(true);
			$docsWritten = 0;
			while ($docsWritten < $docCount) {
				$insertAtOnce = ($docCount - $docsWritten > 1000)
					? 1000
					: $docCount - $docsWritten;

				$docs = array();
				for ($i = 0; $i < $insertAtOnce; $i++) {
					$docs[] = array(
						'_id' => CouchDb::uuid(),
						'foo' => 'bar'
					);
				}
				$db->send('post', '/benchmark_db/_bulk_docs',  compact('docs'));
				$docsWritten = $docsWritten + $insertAtOnce;
				echo '.';
			}
			$insertEnd = microtime(true);
			break;
		case 'single_insert':
			$insertStart = microtime(true);
			for ($i = 0; $i < $docCount; $i++) {
				$db->send('put', sprintf('/benchmark_db/%s', CouchDb::uuid()), array(
					'foo' => 'bar',
				));
			}
			$insertEnd = microtime(true);
			echo '.';
			break;
	}

	clearstatcache();
	$beforeCompact = array(
		'stats' => $db->send('get', '/benchmark_db'),
		'fileSize' => filesize('C:\Program Files (x86)\Apache Software Foundation\ReplicaCDB\var\lib\couchdb\benchmark_db.couch'),
	);

	$compactStart = microtime(true);
	$r = $db->send('post', '/benchmark_db/_compact');
	while ($status = $db->send('get', '/benchmark_db')) {
		if (!$status['compact_running']) {
			break;
		}
		echo 'x';
		usleep(1000000);
	}
	$compactEnd = microtime(true);

	clearstatcache();
	$afterCompact = array(
		'stats' => $db->send('get', '/benchmark_db'),
		'fileSize' => filesize('C:\Program Files (x86)\Apache Software Foundation\ReplicaCDB\var\lib\couchdb\benchmark_db.couch'),
	);

	echo "\n\n";
	echo sprintf(
		"doc count (before compact): %s\n".
		"doc count (after compact): %s\n".
		"insert time: %s sec\n".
		"insert time / doc: %s ms\n".
		"compact time: %s sec\n".
		"compact time / doc: %s ms\n".
		"disk size (before compact): %s bytes\n".
		"disk size (after compact): %s bytes\n".
		".couch size (before compact): %s bytes\n".
		".couch size (after compact): %s bytes\n".
		".couch size / doc (before compact): %s bytes\n".
		".couch size / doc (after compact): %s bytes\n\n",

		$beforeCompact['stats']['doc_count'],
		$afterCompact['stats']['doc_count'],
		round($insertEnd - $insertStart, 4),
		($beforeCompact['stats']['doc_count'])
			? round((($insertEnd - $insertStart) * 1000) / $beforeCompact['stats']['doc_count'], 2)
			: 'n/a',
		round($compactEnd - $compactStart, 4),
		($beforeCompact['stats']['doc_count'])
			? round((($compactEnd - $compactStart) * 1000) / $beforeCompact['stats']['doc_count'], 2)
			: 'n/a',
		$beforeCompact['stats']['disk_size'],
		$afterCompact['stats']['disk_size'],
		$beforeCompact['fileSize'],
		$afterCompact['fileSize'],
		($beforeCompact['stats']['doc_count'])
			? round($beforeCompact['fileSize'] / $beforeCompact['stats']['doc_count'], 2)
			: 'n/a',
		($afterCompact['stats']['doc_count'])
			? round($afterCompact['fileSize'] / $afterCompact['stats']['doc_count'], 2)
			: 'n/a'
	);
}

class CouchDb {
	public $config = array(
		'host' => '10.222.1.157',
		'port' => 8984
	);

	public function __construct($config = array()) {
		$this->config = $config + $this->config;
	}

	public function send($method, $resource, $document = array()) {
		$url = sprintf(
			'http://%s:%s%s',
			$this->config['host'],
			$this->config['port'],
			$resource
		);

		$curlOptions = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CUSTOMREQUEST => strtoupper($method),
			//add this header to CouchDB 1.6.1, otherwise server return 415 error code
			CURLOPT_HTTPHEADER => array('Content-Type:application/json'),
		);

		if (!empty($document)) {
			$curlOptions[CURLOPT_POSTFIELDS] = json_encode($document);
		}


		$curl = curl_init($url);
		curl_setopt_array($curl, $curlOptions);
		$r = curl_exec($curl);

		return json_decode($r, true);
	}

	public static function uuid() {
		return substr(sha1(uniqid(mt_rand(), true)), 0, 32);

	}
}

?>