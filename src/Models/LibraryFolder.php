<?php
namespace A17\Twill\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryFolder extends Model
{
    protected $fillable = ['library', 'name', 'path', 'parent_id'];
}
