class Central
{
  public $resource;

  public function __construct(array $connectionConfig = [
    'telnet_suite_core_host'=> "127.0.0.1",
    'telnet_suite_core_port'=>"5999"
  ]) {
    $this->resource = $this->connectToSuiteResource($connectionConfig);
  }

  public function __destruct()
  {
    fclose($this->resource);
  }

  private function connectToSuiteResource(array $connectionConfig) {
    $resource = @fsockopen($connectionConfig['telnet_suite_core_host'], $connectionConfig['telnet_suite_core_port'], $error_code, $erro_message);
    if (!$resource) die('<br>Connect Error (' . $error_code . ') ' . $erro_message . "\n");
    stream_set_timeout($resource, 3);
    return $resource;
  }

  private function execCommand(string $command, $sanitizeString = TRUE): ?string
  {
    fputs($this->resource, "$command \r\n");
    if ($sanitizeString) {
      return $this->prepareString($this->resource);
    }
  }

  private function prepareString($resource) {
    if (!$resource) throw new Exception("Resource is not a valid resource");
    $resultedString = "";
    while (!feof($resource)) {
      $resultedString .= fgets($resource, 4096);
      if (strpos($resultedString, "\r\n\r\n") !== false) return str_replace("\r\n\r\n", "", $resultedString);
      if (strpos($resultedString, "Invalido") !== false) return $resultedString;
    }
    return $resultedString;
  }

  public function statusRamalFromClient($cliente_id)
  {
    return $this->execCommand("lista_status_ramais_empresa $cliente_id json");
  }

  public function statusRamal($ramal)
  {
    return $this->execCommand("lista_status_ramal $ramal json");
  }
}

class CommandInterface
{
  private const COMMANDS = [
    'ramal:status:client',
    'ramal:status'
  ];

  private static $command;
  private static $method;
  
  public static function validations(array $argv)
  {
    self::$command = $argv;

    if (count(self::$command) < 2) {
      echo "Usage: php teste.php <command> <params> \n";
      exit;
    }

    if (!in_array(self::$command[1], self::COMMANDS)) {
      echo "Command not found \n";
      self::showCommands();
      exit;
    }
    
    self::$method = self::convertCommandToMethod();

    if(!method_exists(self::class, self::$method)) {
      throw new Exception("Method not foun in class", 1);
    }

  }

  private static function convertDelimiterToCamelCase(string $string, string $delimiter = ':'): string
  {
    $string = explode($delimiter, $string);
    $string = array_map(function ($string) {
      return ucfirst($string);
    }, $string);
    return implode('', $string);
  }

  private static function convertCommandToMethod(): string {
    return self::convertDelimiterToCamelCase(self::$command[1]);
  }

  public static function run(array $argv)
  {
    self::validations($argv);
    self::{self::$method}();
  }

  private static function ramalStatusClient()
  {
    $central = new Central();
    echo $central->statusRamalFromClient(self::getParam());
    $central = null;
  }

  private static function ramalStatus()
  {
    $central = new Central();
    echo $central->statusRamal(self::getParam());
    $central = null;
  }

  private static function getParam() {
    return self::$command[2];
  }

  public static function showCommands()
  {
    echo "Commands: \n";
    foreach (self::COMMANDS as $command) {
      echo " - $command \n";
    }
  }
}

CommandInterface::run($argv);
