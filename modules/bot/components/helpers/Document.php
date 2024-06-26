<?php

namespace app\modules\bot\components\helpers;

/**
 * Class Document
 *
 * @package app\modules\bot\components
 */
class Document
{
    /**
     * @var string|null
     */
    private $fileId;

    /**
     * @param string|null $fileId
     */
    public function __construct(?string $fileId)
    {
        $this->fileId = $fileId;
    }

    /**
     * @return string|null
     */
    public function getFileId()
    {
        return $this->fileId;
    }

    /**
     * @return boolean
     */
    public function isNull()
    {
        return $this->fileId === null;
    }
}
