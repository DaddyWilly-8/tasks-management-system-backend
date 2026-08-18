<?php

namespace App\Services;

use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected Auth $auth;
    protected Database $database;
    protected Messaging $messaging;

    public function __construct()
    {
        try {
            // Only initialize what we need
            $this->auth = Firebase::auth();
            $this->database = Firebase::database();
            $this->messaging = Firebase::messaging();
            
            Log::info('Firebase service initialized successfully');
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase service: ' . $e->getMessage());
            throw $e;
        }
    }

    // Auth methods
    public function createUser(string $email, string $password, array $additionalData = [])
    {
        try {
            $userProperties = [
                'email' => $email,
                'password' => $password,
                ...$additionalData
            ];
            
            return $this->auth->createUser($userProperties);
        } catch (\Exception $e) {
            Log::error('Failed to create user: ' . $e->getMessage());
            throw $e;
        }
    }

    public function verifyUserToken(string $idToken)
    {
        try {
            return $this->auth->verifyIdToken($idToken);
        } catch (\Exception $e) {
            Log::error('Failed to verify token: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteUser(string $uid)
    {
        try {
            $this->auth->deleteUser($uid);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete user: ' . $e->getMessage());
            return false;
        }
    }

    // Database methods (Realtime Database)
    public function setData(string $path, $data)
    {
        return $this->database->getReference($path)->set($data);
    }

    public function getData(string $path)
    {
        return $this->database->getReference($path)->getValue();
    }

    public function pushData(string $path, $data)
    {
        return $this->database->getReference($path)->push($data);
    }

    public function updateData(string $path, $data)
    {
        return $this->database->getReference($path)->update($data);
    }

    public function deleteData(string $path)
    {
        return $this->database->getReference($path)->remove();
    }
}