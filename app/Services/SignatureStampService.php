<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SignatureStampService
{
    /**
     * مهر زدن روی فایل آپلودی با استفاده از تصویر مهر پزشک
     *
     * @param UploadedFile $file فایل اصلی (تصویر پیوست)
     * @param string|null $stampRelativePath مسیر ذخیره مهر پزشک در دیسک public
     * @param string $saveDirectory مسیر پوشه ذخیره نهایی فایل
     * @return string مسیر فایل ذخیره‌شده روی storage public
     */
    public function applyStampAndSave(UploadedFile $file, ?string $stampRelativePath, string $saveDirectory): string
    {
        $mime = $file->getClientMimeType();
        $isImage = in_array($mime, ['image/jpeg', 'image/png', 'image/jpg', 'image/webp']);

        // اگر فایل تصویر نبود یا پزشک مهر ثبت‌شده نداشت، فایل بدون تغییر ذخیره شود
        if (!$isImage || !$stampRelativePath || !Storage::disk('public')->exists($stampRelativePath)) {
            return $file->store($saveDirectory, 'public');
        }

        $stampFullPath = Storage::disk('public')->path($stampRelativePath);
        $sourcePath = $file->getRealPath();

        // بارگذاری تصویر اصلی با کتابخانه GD
        $mainImage = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => null,
        };

        if (!$mainImage) {
            return $file->store($saveDirectory, 'public');
        }

        // بارگذاری تصویر مهر
        $stampInfo = @getimagesize($stampFullPath);
        $stampMime = $stampInfo['mime'] ?? '';
        $stampImage = match ($stampMime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($stampFullPath),
            'image/png' => @imagecreatefrompng($stampFullPath),
            'image/webp' => @imagecreatefromwebp($stampFullPath),
            default => null,
        };

        if (!$stampImage) {
            imagedestroy($mainImage);
            return $file->store($saveDirectory, 'public');
        }

        // ابعاد تصویر اصلی
        $mainWidth = imagesx($mainImage);
        $mainHeight = imagesy($mainImage);

        // ابعاد اولیه مهر
        $stampWidth = imagesx($stampImage);
        $stampHeight = imagesy($stampImage);

        // محاسبه سایز متناسب مهر (مثلاً ۲۰٪ عرض تصویر اصلی)
        $targetStampWidth = (int) ($mainWidth * 0.22);
        if ($targetStampWidth < 120) $targetStampWidth = min(120, $mainWidth);
        $targetStampHeight = (int) ($stampHeight * ($targetStampWidth / $stampWidth));

        // ایجاد نسخه ریسایز شده مهر با حفظ ترنسپرنسی
        $resizedStamp = imagecreatetruecolor($targetStampWidth, $targetStampHeight);
        imagealphablending($resizedStamp, false);
        imagesavealpha($resizedStamp, true);
        $transparent = imagecolorallocatealpha($resizedStamp, 0, 0, 0, 127);
        imagefilledrectangle($resizedStamp, 0, 0, $targetStampWidth, $targetStampHeight, $transparent);
        imagecopyresampled($resizedStamp, $stampImage, 0, 0, 0, 0, $targetStampWidth, $targetStampHeight, $stampWidth, $stampHeight);

        // موقعیت درج مهر: گوشه پایین سمت چپ با مارجین ۳۰ پیکسل
        $margin = (int) ($mainWidth * 0.03);
        $destX = $margin;
        $destY = $mainHeight - $targetStampHeight - $margin;
        if ($destY < 0) $destY = 0;

        // ترکیب مهر روی تصویر اصلی
        imagealphablending($mainImage, true);
        imagecopy($mainImage, $resizedStamp, $destX, $destY, 0, 0, $targetStampWidth, $targetStampHeight);

        // تولید نام تصادفی و ذخیره در مسیر مقصد
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $fileName = \Illuminate\Support\Str::random(40) . '.' . $extension;
        $relativeDestPath = trim($saveDirectory, '/') . '/' . $fileName;
        $absoluteDestPath = Storage::disk('public')->path($relativeDestPath);

        // اطمینان از وجود دایرکتوری مقصد
        $dir = dirname($absoluteDestPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // ذخیره فایل پردازش‌شده
        match ($mime) {
            'image/png' => imagepng($mainImage, $absoluteDestPath, 8),
            'image/webp' => imagewebp($mainImage, $absoluteDestPath, 90),
            default => imagejpeg($mainImage, $absoluteDestPath, 90),
        };

        // آزادسازی حافظه
        imagedestroy($mainImage);
        imagedestroy($stampImage);
        imagedestroy($resizedStamp);

        return $relativeDestPath;
    }
}
