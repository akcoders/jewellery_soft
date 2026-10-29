<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class PwaAttachmentCaptureWorkflowTest extends CIUnitTestCase
{
    public function testOrderPhotosOfferCameraAndGalleryWhileDocumentsKeepTheirPicker(): void
    {
        foreach (['jewellery_mobile', 'jewellery_pwa'] as $app) {
            $screen = $this->screen('order_create_screen.dart', $app);

            $this->assertStringContainsString('AppImagePicker().pickImages(', $screen);
            $this->assertStringContainsString('FilePicker.platform.pickFiles(', $screen);
            $this->assertStringContainsString("allowedExtensions: ['pdf', 'dwg', 'dxf']", $screen);
            $this->assertStringContainsString("title: 'Add reference photos'", $screen);
            $this->assertStringContainsString("const Text('Choose PDF / CAD file')", $screen);
        }
    }

    public function testNonFollowupAttachmentsUseTheSharedCameraAndGalleryChooser(): void
    {
        foreach (['jewellery_mobile', 'jewellery_pwa'] as $app) {
            $files = [
                'task_scheduler_screen.dart',
                'diamond_requirements_screen.dart',
                'transaction_create_screen.dart',
                'issuement_create_screen.dart',
                'purchase_create_screen.dart',
                'order_detail_screen.dart',
                $app === 'jewellery_mobile'
                    ? 'diamond_bag_create_screen.dart'
                    : 'order_receiving_screen.dart',
            ];
            foreach ($files as $file) {
                $screen = $this->screen($file, $app);
                $this->assertStringContainsString(
                    'AppImagePicker().pickImage',
                    $screen,
                    $app . '/' . $file . ' must use the shared photo chooser'
                );
                $this->assertStringNotContainsString('source: ImageSource.camera', $screen);
                $this->assertStringNotContainsString('allowGallery: false', $screen);
            }
        }
    }

    public function testNativeFollowupPickerObeysEmployeeGalleryControl(): void
    {
        $detail = $this->screen('order_detail_screen.dart');
        $form = $this->screen('order_followup_form_screen.dart');

        $this->assertStringContainsString("data['followup_gallery_enabled']", $detail);
        $this->assertStringContainsString('allowGallery: _followupGalleryEnabled', $detail);
        $this->assertStringContainsString('allowGallery: widget.allowGallery', $form);
        $this->assertStringContainsString('imageSource: _pickedSource.name', $form);
        $this->assertStringContainsString('Camera-only evidence is enabled for your account.', $form);
    }

    private function screen(string $file, string $app = 'jewellery_mobile'): string
    {
        return (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/' . $app . '/screens/' . $file
        );
    }
}
