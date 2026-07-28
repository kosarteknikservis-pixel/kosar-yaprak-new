<?php
declare(strict_types=1);
/**
 * Lightweight MMDB Reader for PHP — GeoLite2 City (adapted from shared panel codebase).
 */

final class CloakerMMDBReader
{
    private $fp;
    /** @var array<string,mixed>|null */
    private $metadata;
    private int $nodeCount = 0;
    private int $recordSize = 0;
    private int $searchTreeSize = 0;
    private int $ipVersion = 4;
    private int $dataSectionStart = 0;

    public function __construct(string $dbFile)
    {
        $this->fp = fopen($dbFile, 'rb');
        if (!$this->fp || !is_resource($this->fp)) {
            throw new RuntimeException('Could not open database file');
        }
        $this->loadMetadata();
    }

    private function loadMetadata(): void
    {
        $meta = stream_get_meta_data($this->fp);
        /** @phpstan-ignore-next-line */
        $fileSize = (int) filesize($meta['uri']);
        if ($fileSize <= 0) {
            throw new RuntimeException('Invalid MMDB file size');
        }
        fseek($this->fp, $fileSize - 128 * 1024);
        $buffer = fread($this->fp, 128 * 1024);
        if ($buffer === false) {
            throw new RuntimeException('MMDB read failed');
        }
        $marker = "\xAB\xCD\xEFMaxMind.com";
        $pos = strrpos((string) $buffer, $marker);
        if ($pos === false) {
            throw new RuntimeException('Metadata marker not found');
        }
        $metadataPos = ($fileSize - 128 * 1024) + $pos + strlen($marker);
        fseek($this->fp, $metadataPos);
        $meta = $this->decodeNode();
        if (!is_array($meta)) {
            throw new RuntimeException('Invalid MMDB metadata');
        }
        $this->metadata = $meta;
        $this->nodeCount = (int) ($this->metadata['node_count'] ?? 0);
        $this->recordSize = (int) ($this->metadata['record_size'] ?? 0);
        $this->ipVersion = (int) ($this->metadata['ip_version'] ?? 4);
        $this->searchTreeSize = (int) ($this->nodeCount * ($this->recordSize * 2 / 8));

        fseek($this->fp, $this->searchTreeSize);
        $check = fread($this->fp, 16);
        $this->dataSectionStart = $check === str_repeat("\x00", 16)
            ? $this->searchTreeSize + 16
            : $this->searchTreeSize;
    }

    /** @return array<string,mixed>|null */
    public function get(string $ip): ?array
    {
        $packed = inet_pton($ip);
        if ($packed === false) {
            return null;
        }
        $bytes = array_values(unpack('C*', $packed));
        $bitCount = count($bytes) * 8;

        $node = 0;
        if ($this->ipVersion === 6 && $bitCount === 32) {
            for ($i = 0; $i < 96; ++$i) {
                $node = $this->readNode($node, 0);
            }
        }

        for ($i = 0; $i < $bitCount; ++$i) {
            if ($node >= $this->nodeCount) {
                break;
            }
            $bit = ($bytes[(int) floor($i / 8)] >> (7 - ($i % 8))) & 1;
            $node = $this->readNode($node, $bit);
        }

        if ($node >= $this->nodeCount) {
            return $this->resolveData($node);
        }

        return null;
    }

    private function readNode(int $nodeNumber, int $index): int
    {
        $nodeByteSize = (int) (($this->recordSize * 2) / 8);
        $offset = $nodeNumber * $nodeByteSize;
        fseek($this->fp, $offset);
        $bytes = fread($this->fp, $nodeByteSize);
        if ($bytes === false || strlen($bytes) !== $nodeByteSize) {
            return 0;
        }

        if ($this->recordSize === 24) {
            if ($index === 0) {
                return (ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]);
            }

            return (ord($bytes[3]) << 16) | (ord($bytes[4]) << 8) | ord($bytes[5]);
        }
        if ($this->recordSize === 28) {
            $middle = ord($bytes[3]);
            if ($index === 0) {
                return (($middle & 0xF0) << 20) | (ord($bytes[0]) << 16) | (ord($bytes[1]) << 8) | ord($bytes[2]);
            }

            return (($middle & 0x0F) << 24) | (ord($bytes[4]) << 16) | (ord($bytes[5]) << 8) | ord($bytes[6]);
        }
        if ($this->recordSize === 32) {
            if ($index === 0) {
                return (ord($bytes[0]) << 24) | (ord($bytes[1]) << 16) | (ord($bytes[2]) << 8) | ord($bytes[3]);
            }

            return (ord($bytes[4]) << 24) | (ord($bytes[5]) << 16) | (ord($bytes[6]) << 8) | ord($bytes[7]);
        }

        return 0;
    }

    /** @return array<string,mixed>|null */
    private function resolveData(int $node): ?array
    {
        $pointer = $node - $this->nodeCount;
        fseek($this->fp, $this->dataSectionStart + $pointer);

        return $this->decodeNode();
    }

    /** @return mixed|null */
    private function decodeNode()
    {
        $ctrlByte = fread($this->fp, 1);
        if ($ctrlByte === false || $ctrlByte === '') {
            return null;
        }
        $ctrl = ord($ctrlByte);
        $type = $ctrl >> 5;
        if ($type === 0) {
            $extByte = fread($this->fp, 1);
            if ($extByte === false) {
                return null;
            }
            $type = ord($extByte) + 7;
        }

        $size = $ctrl & 0x1F;
        if ($size >= 29) {
            $bytesToRead = $size - 28;
            $sBuf = fread($this->fp, $bytesToRead);
            $size = 0;
            for ($i = 0; $i < strlen((string) $sBuf); ++$i) {
                /** @phpstan-ignore-next-line */
                $size = ($size << 8) | ord($sBuf[$i]);
            }
            $size += ([0, 29, 285, 65821][strlen((string) $sBuf)]);
        }

        switch ($type) {
            case 1:
                $pSize = ($ctrl >> 3) & 0x03;
                $pBuf = fread($this->fp, $pSize + 1);
                $pVal = 0;
                for ($i = 0; $i < strlen((string) $pBuf); ++$i) {
                    /** @phpstan-ignore-next-line */
                    $pVal = ($pVal << 8) | ord($pBuf[$i]);
                }
                $pointer = 0;
                if ($pSize === 0) {
                    $pointer = (($ctrl & 0x07) << 8) | $pVal;
                } elseif ($pSize === 1) {
                    $pointer = ((($ctrl & 0x07) << 16) | $pVal) + 2048;
                } elseif ($pSize === 2) {
                    $pointer = ((($ctrl & 0x07) << 24) | $pVal) + 526336;
                } else {
                    $pointer = $pVal;
                }
                $oldPos = (int) ftell($this->fp);
                fseek($this->fp, $this->dataSectionStart + $pointer);
                $res = $this->decodeNode();
                fseek($this->fp, $oldPos);

                return $res;
            case 2:
                return ($size > 0) ? fread($this->fp, $size) : '';
            case 3:
                $raw = fread($this->fp, 8);
                if ($raw === false || strlen($raw) !== 8) {
                    return null;
                }

                return unpack('d', strrev($raw))[1];
            case 5:
            case 6:
            case 9:
            case 10:
                $val = 0;
                if ($size > 0) {
                    $iBuf = fread($this->fp, $size);
                    for ($i = 0; $i < strlen((string) $iBuf); ++$i) {
                        /** @phpstan-ignore-next-line */
                        $val = ($val << 8) | ord($iBuf[$i]);
                    }
                }

                return $val;
            case 7:
                $map = [];
                for ($i = 0; $i < $size; ++$i) {
                    $k = $this->decodeNode();
                    $v = $this->decodeNode();
                    if ($k !== null) {
                        $map[$k] = $v;
                    }
                }

                return $map;
            case 11:
                $arr = [];
                for ($i = 0; $i < $size; ++$i) {
                    $arr[] = $this->decodeNode();
                }

                return $arr;
            case 4:
                return ($size > 0) ? fread($this->fp, $size) : '';
            case 8:
                return $size !== 0;
            default:
                return null;
        }
    }

    public function __destruct()
    {
        if ($this->fp && is_resource($this->fp)) {
            fclose($this->fp);
        }
    }
}
