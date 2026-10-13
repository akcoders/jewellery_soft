<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class PwaAttachmentCaptureWorkflowTest extends CIUnitTestCase
{
    public function testOrderPhotosUseTheGalleryWhileDocumentsKeepTheirPicker(): void
    {
        $screen = $this->screen('order_create_screen.dart');

        $this->assertStringContainsString('pickMultiImage(', $screen);
        $this->assertStringContainsString('FilePicker.platform.pickFiles(', $screen);
        $this->assertStringContainsString("allowedExtensions: ['pdf', 'dwg', 'dxf']", $screen);
        $this->assertStringContainsString("const Text('Choose photos')", $screen);
        $this->assertStringContainsString("const Text('Choose PDF / CAD file')", $screen);
    }

    public function testEveryNonOrderPhotoAttachmentOpensTheCameraDirectly(): void
    {
        foreach ([
            'task_scheduler_screen.dart',
            'diamond_requirements_screen.dart',
            'transaction_create_screen.dart',
            'issuement_create_screen.dart',
            'purchase_create_screen.dart',
        ] as $file) {
            $screen = $this->screen($file);
            $this->assertStringContainsString(
                'source: ImageSource.camera',
                $screen,
                $file . ' must launch the camera'
            );
            $this->assertStringNotContainsString(
                'ImageSource.gallery',
                $screen,
                $file . ' must not offer gallery upload'
            );
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

    private function screen(string $file): string
    {
        return (string) file_get_contents(
            ROOTPATH . 'app_kit/FlutKit/lib/jewellery_mobile/screens/' . $file
        );
    }
}
