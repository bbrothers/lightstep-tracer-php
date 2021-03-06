<?php
namespace LightStepBase\Client\Transports;

use LightStepBase\Client\SystemLogger;
use Psr\Log\LoggerInterface;

class TransportHTTPPROTO {

    protected $_host = '';
    protected $_port = 0;
    protected $_verbose = 0;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger = null) {

        $this->logger = $logger ?: new SystemLogger;
    }

    /**
     * Assigns the variables that are required for connectivity.
     *
     * @param array $options Options for how to configure the transport. Expected keys are:
     * 'verbose' - if true, payloads and errors will be logged
     * 'collector_host' - hostname of the collector for the url
     * 'collector_port' - port of the collector for the url
     * 'collector_secure' - if true, will use SSL
     */
    public function ensureConnection($options) {
        $this->_verbose = $options['verbose'];

        $this->_host = $options['collector_host'];
        $this->_port = $options['collector_port'];

        // The prefixed protocol is only needed for secure connections
        if ($options['collector_secure'] == true) {
            $this->_host = "ssl://" . $this->_host;
        }
    }

    public function flushReport($auth, $report) {
        if (is_null($auth) || is_null($report)) {
            if ($this->_verbose > 0) {
                $this->logger->error("Auth or report not set.");
            }
            return NULL;
        }

        $content = $report->toProto($auth)->serializeToString();

        if ($this->_verbose >= 3) {
            $this->logger->debug('Report to be flushed', ['content' => $content]);
        }

        $header = "Host: " . $this->_host . "\r\n";
        $header .= "User-Agent: LightStep-PHP\r\n";
        $header .= "Accept: application/octet-stream\r\n";
        $header .= "Content-Type: application/octet-stream\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n";
        $header .= "Connection: keep-alive\r\n\r\n";

        // Use a persistent connection when possible
        $fp = @pfsockopen($this->_host, $this->_port, $errno, $errstr);
        if (!$fp) {
            if ($this->_verbose > 0) {
                $this->logger->error($errstr);
            }
            return NULL;
        }

        @fwrite($fp, "POST /api/v2/reports HTTP/1.1\r\n");
        @fwrite($fp, $header . $content);
        @fflush($fp);
        @fclose($fp);

        return NULL;
    }
}
