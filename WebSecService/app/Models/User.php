<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable
{
    use HasApiTokens, HasRoles;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    // Management Levels
    const MANAGEMENT_LEVEL_LOW = 'low';      // Only handle customer tasks
    const MANAGEMENT_LEVEL_MIDDLE = 'middle'; // Handle customers and low-level management
    const MANAGEMENT_LEVEL_HIGH = 'high';    // Full system access

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'credits',
        'theme_dark_mode',
        'theme_color',
        'management_level',
        'google_id',
        'facebook_id',
        'linkedin_id',
        'github_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
        'facebook_id',
        'linkedin_id',
        'github_id',
        'certificate_serial',
        'certificate_dn',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'credits' => 'decimal:2',
            'theme_dark_mode' => 'boolean',
            'last_certificate_login' => 'datetime',
        ];
    }

    /**
     * Get the orders for the user.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the customer service cases assigned to this user.
     */
    public function assignedCases()
    {
        return $this->hasMany(CustomerServiceCase::class, 'assigned_to');
    }

    /**
     * Get the product comments made by this user.
     */
    public function productComments()
    {
        return $this->hasMany(ProductComment::class);
    }

    /**
     * Check if the user has a specified management level or higher
     */
    public function hasManagementLevel(string $level): bool
    {
        if (empty($this->management_level)) {
            return false;
        }

        if ($this->management_level === self::MANAGEMENT_LEVEL_HIGH) {
            return true; // High level has access to everything
        }

        if ($this->management_level === self::MANAGEMENT_LEVEL_MIDDLE) {
            return $level !== self::MANAGEMENT_LEVEL_HIGH; // Middle has access to middle and low
        }

        // Low level only has access to low
        return $this->management_level === $level && $level === self::MANAGEMENT_LEVEL_LOW;
    }

    /**
     * Check if user is a low-level manager (can only handle customer tasks)
     */
    public function isLowLevelManager(): bool
    {
        return $this->management_level === self::MANAGEMENT_LEVEL_LOW;
    }

    /**
     * Check if user is a middle-level manager (can handle customers and low-level management)
     */
    public function isMiddleLevelManager(): bool
    {
        return $this->management_level === self::MANAGEMENT_LEVEL_MIDDLE;
    }

    /**
     * Check if user is a high-level manager (full system access)
     */
    public function isHighLevelManager(): bool
    {
        return $this->management_level === self::MANAGEMENT_LEVEL_HIGH;
    }

    /**
     * Check if the user has enough credits for a purchase
     */
    public function hasEnoughCredits(float $amount): bool
    {
        return $this->credits >= $amount;
    }

    /**
     * Deduct credits from user account with validation
     */
    public function deductCredits(float $amount): bool
    {
        // Validate amount is positive
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }
        
        if (!$this->hasEnoughCredits($amount)) {
            return false;
        }

        $this->credits -= $amount;
        $this->save();
        
        // Log the transaction
        Log::info("Deducted {$amount} credits from user ID {$this->id}. New balance: {$this->credits}");
        
        return true;
    }

    /**
     * Add credits to user account with validation
     */
    public function addCredits(float $amount): void
    {
        // Validate amount is positive
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }
        
        // Cap maximum amount that can be added in a single transaction for security
        $maxSingleTransaction = config('app.max_credit_transaction', 10000);
        if ($amount > $maxSingleTransaction) {
            throw new \InvalidArgumentException("Cannot add more than {$maxSingleTransaction} credits in a single transaction.");
        }
        
        $this->credits += $amount;
        $this->save();
        
        // Log the transaction
        Log::info("Added {$amount} credits to user ID {$this->id}. New balance: {$this->credits}");
    }

    /**
     * Check if the user has editor-level permissions
     */
    public function hasEditorLevelPermissions(): bool
    {
        // Get the Editor role and its permissions
        $editorRole = \Spatie\Permission\Models\Role::findByName('Editor');
        if (!$editorRole) {
            return false;
        }

        // Get all editor permissions
        $editorPermissions = $editorRole->permissions->pluck('name')->toArray();

        // Check if the user has all editor permissions
        foreach ($editorPermissions as $permission) {
            if (!$this->hasPermissionTo($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get user's last login attempts
     */
    public function loginAttempts()
    {
        return $this->hasMany(LoginAttempt::class);
    }

    /**
     * Get user's active sessions
     */
    public function activeSessions()
    {
        return $this->hasMany(Session::class)->where('last_activity', '>', now()->subHours(2)->timestamp);
    }

    /**
     * Check if user's password needs to be changed
     */
    public function passwordNeedsChange(): bool
    {
        $lastPasswordChange = $this->password_changed_at ?? $this->created_at;
        $maxPasswordAge = config('auth.password_lifetime', 90); // 90 days default
        return $lastPasswordChange->addDays($maxPasswordAge)->isPast();
    }

    /**
     * Get user's security score (0-100)
     */
    public function getSecurityScore(): int
    {
        $score = 0;
        
        // Base checks (50 points total)
        if ($this->email_verified_at) $score += 10;
        if (strlen($this->password) >= 12) $score += 10;
        if ($this->two_factor_enabled) $score += 15;
        if (!$this->passwordNeedsChange()) $score += 15;
        
        // Additional security measures (50 points total)
        if ($this->certificate_serial) $score += 15;
        if ($this->recovery_email) $score += 10;
        if ($this->security_questions_set) $score += 10;
        if ($this->last_security_audit) $score += 15;
        
        return min(100, $score);
    }

    /**
     * Get user's notification preferences
     */
    public function notificationPreferences()
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /**
     * Check if user has completed profile
     */
    public function hasCompleteProfile(): bool
    {
        $requiredFields = [
            'name',
            'email',
            'phone',
            'address',
            'date_of_birth',
            'recovery_email'
        ];

        foreach ($requiredFields as $field) {
            if (empty($this->$field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get profile completion percentage
     */
    public function getProfileCompletionPercentage(): int
    {
        $fields = [
            'name' => 15,
            'email' => 15,
            'phone' => 10,
            'address' => 15,
            'date_of_birth' => 10,
            'recovery_email' => 10,
            'profile_picture' => 10,
            'bio' => 5,
            'social_links' => 5,
            'security_questions_set' => 5
        ];

        $score = 0;
        foreach ($fields as $field => $weight) {
            if (!empty($this->$field)) {
                $score += $weight;
            }
        }

        return min(100, $score);
    }

    protected $dates = [
        'created_at',
        'updated_at',
        'email_verified_at',
        'last_login_at',
        'password_changed_at',
        'last_security_audit',
        'last_certificate_login'
    ];
}
