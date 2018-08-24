<?php

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
    }/*,
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
    }*/
];

class Task extends Threaded
{
    private $value;

    public function __construct(array $i, int $id)
    {
        $this->value = $i;
        $this->id = $id;
    }

    public function checkLink($url)
    {
        $headers = get_headers($url);
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
        foreach ($this->value as $i => $line)
        {
            print("Thread {$this->id}, Run line -> $line\n");

            usleep(1000);

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
            $name  = $id ?: trim(preg_replace("/(?:.(?!,))+\$/", "", $line), ",");

            var_dump("ID: $id", "NAME: $name", "LOGO: $logo", "GROUP: $group");

            if (strpos($name, "#") !== false){
                //continue;
            }

            if (!$group || strpos($group, "#EXTINF") !== false || strpos($group, "#extinf") !== false){
                $group = "Sem categoria";
            }

            $group = $mp4 ? "[MP4] {$group}" : $group;

            if (!$this->checkLink($url)){
                print("Thread {$this->id} Link $url off\n");
                continue;
            }
            else
                print("Thread {$this->id}Link $url on\n");

            $channels[$group][] = [
                "url"  => $url,
                "name" => $name,
                "logo" => $logo
            ];

            $m3u_file .= "#EXTINF:-1 tvg-id=\"{$id}\" tvg-logo=\"{$logo}\" group-title=\"{$group}\",{$name}";
            $m3u_file .= PHP_EOL;
            $m3u_file .= $url;
            $m3u_file .= PHP_EOL . PHP_EOL;

            print($m3u_file);
        }
    }
}

function main(){
    global $extras, $path, $links;
    $threads  = 0;
    $lists    = [];
    $channels = [];
    $m3u_file =
        "#EXTM3U" . PHP_EOL . PHP_EOL .
        "#PLAYLISTV:  pltv-name=\"Bergamota list\" pltv-description=\"LISTA DE CANAIS\" pltv-author=\"Bergamota Inc.\"" . PHP_EOL . PHP_EOL .
        "############# Atualizado em " . date("d/m/Y H:i:s") . " #############" . PHP_EOL . PHP_EOL;

    foreach ($links as $link => $func){
        $m3u_file .= "############# Agradecimento: {$link} #############" . PHP_EOL . PHP_EOL;
        $lists = array_merge($lists, $func());

        print("Parsed list $link\n");
    }

    //$lists = array_unique(array_merge($lists, $extras));
    $pool = new Pool(sizeof($lists));

    foreach ($lists as $list)
    {
        print("Staring list $list\n");

        $m3u_file .= PHP_EOL;
        $m3u_file .= "############# Fonte: {$list} #############";
        $m3u_file .= PHP_EOL;

        $agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
        $ch    = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE,        false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT,      $agent);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL,            $list);

        $result = curl_exec($ch);
        $lines  = explode(PHP_EOL, $result);

        $pool->submit(new Task($lines, $threads++));
        sleep(10);

        curl_close($ch);
    }

    while ($pool->collect());
    $pool->shutdown();

    //file_put_contents("{$path}list.json", json_encode($channels, JSON_PRETTY_PRINT));
    //file_put_contents("{$path}list.m3u",  $m3u_file);
}


main();

/*
# Create a pool of 4 threads
$pool = new Pool(4);

for ($i = 0; $i < 15000; ++$i)
{
    $pool->submit(new Task($i));
}

while ($pool->collect());

$pool->shutdown();
*/
