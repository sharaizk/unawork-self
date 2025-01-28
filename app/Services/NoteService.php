<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NoteFile;
use App\Models\Utility;
use Exception;

class NoteService
{
    /**
     * Handle file upload and storage for a note.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Note $note
     * @throws Exception
     */
    public function handleFileUpload($request, $note): void
    {
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $file_name = $file->getClientOriginalName();
            $file_path = $note->id . "_" . md5(time()) . "_" . $file_name;
            $file_size = $file->getSize();
            $result = Utility::updateStorageLimit(auth()->id(), $file_size);

            if ($result != 1) {
                throw new Exception(__('Storage limit exceeded.'));
            }

            $dir = 'notes_files/';
            $path = Utility::upload_file($request, 'file', $file_path, $dir, []);
            if ($path['flag'] != 1) {
                throw new Exception($path['msg']);
            }

            // Create a NoteFile entry
            NoteFile::create([
                'note_id' => $note->id,
                'file_name' => $file_name,
                'file_path' => $file_path,
            ]);
        }
    }

    /**
     * Delete all files associated with a note.
     *
     * @param \App\Models\Note $note
     * @return void
     */
    public function deleteAllFiles(Note $note): void
    {
        foreach ($note->files as $file) {
            $file_path = 'notes_files/' . $file->file_path;

            // Adjust storage limit using Utility method
            Utility::changeStorageLimit(auth()->id(), $file_path);

            // Delete the file record
            $file->delete();
        }
    }
}
