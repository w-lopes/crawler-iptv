<?php

$extras = [ 
    "https://bit.ly/faustinotv"
];

$links = [
    "https://listasiptvgratis.com/" => function(){
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTMLFile("https://listasiptvgratis.com/");

        $lists    = [];
        $table    = $dom->getElementsByTagName("table")[2];
        $lines    = explode(PHP_EOL, $table->nodeValue);
        foreach ($lines as $line){
            if (!preg_match("/Copiar/", $line)){
                continue;
            }
            $lists[] = preg_replace("/Link\$/", "", explode(" ", $line)[1]);
        }
        return $lists;
    },
    "https://listaiptvbrasilhd.com.br/" => function(){
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTMLFile("https://listaiptvbrasilhd.com.br/ranking-das-melhores-listas-de-canais-iptv-atualizadas-gratuitas-do-brasil/");

        $lists    = [];
        $table    = $dom->getElementsByTagName("table")->item(1);
        $limit    = 10;
        $count    = 0;

        foreach ($table->getElementsByTagName('tr') as $tr) {
            $as = $tr->getElementsByTagName('a');
            foreach ($as as $i => $a){
                if ($count >= $limit){
                    return $lists;
                }
                $lists[] = trim($a->nodeValue);
                $count++;
            }
        }
        return $lists;
    }
];

class Task extends Threaded
{
    private $value;
    private $m3u;

    public function __construct(array $i, int $id)
    {
        stream_context_set_default(['http' => ['method' => 'HEAD',
                                               'timeout' => 10,
                                               'user_agent' => 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)']]);
    
        $this->value = $i;
        $this->id = $id;
    }

    public function start_m3u()
    {
        $this->m3u = "";
    }

    public function add_m3u($id, $logo, $group, $name, $url)
    {
        $this->m3u .= "#EXTINF:-1 tvg-id=\"{$id}\" tvg-logo=\"{$logo}\" group-title=\"{$group}\",{$name}";
        $this->m3u .= PHP_EOL;
        $this->m3u .= $url;
        $this->m3u .= PHP_EOL . PHP_EOL;

        // $channels[$group][] = [
        //     "url"  => $url,
        //     "name" => $name,
        //     "logo" => $logo
        // ];

        //file_put_contents("{$path}list.json", json_encode($channels, JSON_PRETTY_PRINT));
        //file_put_contents("{$path}list.m3u",  $m3u_file);
    }

    public function write_m3u()
    {
        $this->synchronized(function($thread){
            file_put_contents("lista.m3u", $this->m3u, FILE_APPEND);        
        }, $this);
    }

    public function checkLink($url)
    {
        $headers = @get_headers($url);
        if (!$headers){
            return false;
        }
        $status = explode(" ", $headers[0])[1];
        return $status < 400;
    }

    public function getExtInfo($info, $line)
    {
        if(strpos($line, $info) === false){
            return null;
        }
        return html_entity_decode(preg_replace('/(.*' . $info . '=")([^"]+)(.*)/', '$2', $line));
    }

    public function run()
    {
        $this->start_m3u();

        foreach ($this->value as $i => $line)
        {
            if (strpos($line, "#EXTINF") === false){
                continue;
            }
            if (!isset($this->value[$i + 1])){
                continue;
            }
            $url = trim($this->value[$i + 1]);
            if (!filter_var($url, FILTER_SANITIZE_URL)){
                continue;
            }
            if (!preg_match("/(\.m3u)|(\.mp4)|(\.m3u8)|(\.ts)/", $url)){
                continue;
            }
            $mp4   = strpos($url, ".mp4");

            $group = $this->getExtInfo("group-title", $line);
            $logo  = $this->getExtInfo("tvg-logo", $line);
            $id    = $this->getExtInfo("tvg-id", $line);
            $name  = $this->getExtInfo("tvg-name", $line);

            if(!$name)
            {
                preg_match("/(?:.*,)(.*)/", $line, $name);
                $name = $name[1];
            }

            if (!$group)
                $group = "Sem categoria";

            $group = $mp4 ? "[MP4] {$group}" : $group;

            if (!$this->checkLink($url)){
                print("Thread {$this->id}: Link $name [\033[31mOFF\033[0m]\n");
                continue;
            }
            else
                print("Thread {$this->id}: Link $name [\033[32mON\033[0m]\n");

            $this->add_m3u($id, $logo, $group, $name, $url);
        }

        $this->write_m3u();
    }
}

function main()
{
    global $extras, $path, $links;
    $threads  = 0;
    $lists    = [];
    $channels = [];

    print("Getting main lists.....\n");

    foreach ($links as $link => $func)
    {
        $lists = array_merge($lists, $func());
        print("Parsed list $link [\033[32mOK\033[0m]\n");
    }

    $lists = array_unique(array_merge($lists, $extras));

    $pool = new Pool(sizeof($lists));

    file_put_contents("lista.m3u", "#EXTM3U" . PHP_EOL . PHP_EOL .
    "#PLAYLISTV:  pltv-name=\"Bergamota list\" pltv-description=\"LISTA DE CANAIS\" pltv-author=\"Bergamota Inc.\"" . PHP_EOL . PHP_EOL .
    "############# Atualizado em " . date("d/m/Y H:i:s") . " #############" . PHP_EOL . PHP_EOL);

    foreach ($lists as $list)
    {
        print("Staring list $list [\033[32mOK\033[0m]\n");

        $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
        $ch    = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE,        false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,      $agent);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL,            $list);
        curl_setopt($ch, CURLOPT_TIMEOUT,        15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $result = curl_exec($ch);
        $lines  = explode(PHP_EOL, $result);

        // start a thread for every new list
        $pool->submit(new Task($lines, $threads++));

        curl_close($ch);
    }

    while ($pool->collect());
    $pool->shutdown();
}

main();