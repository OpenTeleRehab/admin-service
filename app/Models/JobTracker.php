<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobTracker extends Model
{
    const QUEUED = 'queued';
    const RUNNING = 'running';
    const COMPLETED = 'completed';
    const FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['job_id', 'status', 'trackable_id', 'trackable_type', 'message'];

    /**
     * Get the model that this job status is associated with.
     *
     * This defines a polymorphic inverse relationship, allowing
     * JobTracker to belong to any "trackable" model (e.g., MfaSetting, UserGroup, etc.).
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function trackable()
    {
        return $this->morphTo();
    }

    /**
     * Create a JobTracker record for a given trackable model.
     *
     * This is a convenience factory method to create a new JobTracker
     * and automatically set the polymorphic relationship.
     *
     * @param int $jobId The ID of the job this status belongs to.
     * @param \Illuminate\Database\Eloquent\Model $trackable The model being tracked.
     * @param string $status The initial status (default: 'queued').
     * @param string $message Optional message describing the job JobTracker.
     * @return static The newly created JobTracker instance.
     */
    public static function createForTrackable($jobId, Model $trackable, $status = 'queued', $message = '')
    {
        return self::create([
            'job_id' => $jobId,
            'status' => $status,
            'trackable_id' => $trackable->id,
            'trackable_type' => get_class($trackable),
            'message' => $message,
        ]);
    }
}
