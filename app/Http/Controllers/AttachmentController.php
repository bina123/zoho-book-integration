<?php

namespace App\Http\Controllers;

use App\Contracts\ZohoBooksClient;
use App\Exceptions\ZohoApiException;
use App\Http\Requests\DownloadAttachmentRequest;
use Symfony\Component\HttpFoundation\Response;

final class AttachmentController extends Controller
{
    public function __construct(protected ZohoBooksClient $booksClient) {}

    public function __invoke(DownloadAttachmentRequest $request): Response
    {
        $type = $request->attachmentType();
        $id = $request->attachmentId();

        try {
            $attachment = $this->booksClient->downloadAttachment($type, $id);
        } catch (ZohoApiException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }

        if ($attachment === null) {
            return response()->json([
                'success' => false,
                'message' => 'No attachment found for this transaction.',
            ], 404);
        }

        $filename = $attachment['filename'] ?: "{$type->value}-{$id}.pdf";
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($attachment['contents'], 200, [
            'Content-Type' => $attachment['content_type'],
            'Content-Disposition' => $disposition.'; filename="'.addslashes($filename).'"',
            'Content-Length' => (string) strlen($attachment['contents']),
            'Cache-Control' => 'private, max-age=0',
        ]);
    }
}
