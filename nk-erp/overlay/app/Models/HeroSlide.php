<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    public const LINKS = ['order' => 'Order form (#order)', 'prices' => 'Prices (#prices)', 'app' => 'Customer app'];
    public const ANIMS = ['slide' => 'Slide in + float', 'fade-float' => 'Fade in + float', 'fade' => 'Fade only'];
    public const BGS = ['#f7f4ec' => 'Cream', '#ffffff' => 'White', '#e6eeff' => 'Light blue', '#efe5cc' => 'Soft gold'];
    protected $fillable = ['position', 'brand', 'size', 'enabled', 'heading', 'description', 'description_bn', 'button_text', 'button_link', 'image', 'animation', 'duration', 'background'];
    protected function casts(): array { return ['enabled' => 'boolean']; }

    /** The public form of a slide, as the website reads it. Images from the admin get a full address. */
    public function publicData(): array
    {
        $img = $this->image;
        if ($img && str_starts_with($img, '/uploads/')) $img = rtrim((string) config('app.url'), '/').$img;
        return ['size' => $this->size, 'brand' => $this->brand, 'heading' => $this->heading, 'en' => $this->description, 'bn' => $this->description_bn,
            'btn' => $this->button_text, 'link' => $this->button_link, 'img' => $img, 'anim' => $this->animation, 'sec' => $this->duration, 'bg' => $this->background];
    }
}
