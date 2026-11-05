<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpRecord extends Model
{
    public const STATUS = ['draft' => 'Draft', 'submitted' => 'Submitted', 'checked' => 'Checked', 'approved' => 'Approved', 'posted' => 'Posted', 'closed' => 'Closed', 'rejected' => 'Rejected'];
    protected $fillable = ['module', 'form', 'record_no', 'data', 'status', 'trail', 'file', 'created_by'];
    protected function casts(): array { return ['data' => 'array', 'trail' => 'array']; }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function val(string $label): string
    {
        foreach (($this->data ?? []) as $k => $v) if (strcasecmp($k, $label) === 0) return (string) $v;
        return '';
    }
    public function num(string $label): float { return (float) preg_replace('/[^0-9.\-]/', '', $this->val($label)); }
}
