<?php

class pastebin
{
	private $folder = './pastebins';
	public $pastes = array();
	function __construct($folder = false)
	{
		if ($folder) {
			$this->folder = $folder;
		}
		$this->folder = rtrim($this->folder, '/');
		if (!file_exists($this->folder)) {
			mkdir($this->folder);
		}
	}
	function downloader()
	{
		while (count($this->pastes) > 0)
		{
			$paste = array_shift($this->pastes);
			$fn = sprintf('%s/%s-%s.txt', $this->folder, $paste, date('Y-m-d'));
			$content = file_get_contents('http://pastebin.com/raw.php?i=' . $paste);
			if (strpos($content, 'requesting a little bit too much') !== false) {
				printf("Throttling... requeuing $s\n", $paste);
				$this->pastes[] = $paste;
				sleep(1);
			} else {
				file_put_contents($fn, $content);
			}
			//$delay = rand(1, 3);
			printf("Downloaded %s, waiting %d sec\n", $paste, $delay);
			//sleep($delay);
		}
	}
	function scraper()
	{
		$doc = new DOMDocument();
		$doc->recover = true;
		@$doc->loadHTMLFile('http://www.pastebin.com/archive');
		$xpath = new DOMXPath($doc);
		$elements = $xpath->query('//table[@class="maintable"]/tr/td[1]/a');
		if ($elements !== null) {
			foreach ($elements as $e) {
				$href = $e->getAttribute('href');
				var_dump($href);
				if (in_array($href, $this->pastes)) {
					printf("%s already seen\n", $href);
				} else {
					$this->pastes[] = substr($href, 1);
				}
			}
		}
	}
}

$p = new pastebin();

while (true) {
	$p->scraper();
	$p->downloader();
	//sleep(rand(6, 12));
}

?>