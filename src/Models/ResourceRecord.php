<?php
declare(strict_types=1);

namespace PounceTech\Models;

class ResourceRecord
{
    public string $name;
    public string $type;
    public int $ttl = 3600;
    public int $priority = 0; // Default value for records not using this field
    public string $data;
}