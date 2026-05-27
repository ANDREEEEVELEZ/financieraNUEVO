<?php

namespace App\Livewire;

use Livewire\Component;
use App\Infrastructure\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;

class NotificationComponent extends Component
{
    public $notifications = [];
    public $unreadCount = 0;
    public $showDropdown = false;

    protected $notificationService;

    public function mount()
    {
        $this->notificationService = app(NotificationService::class);
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        if (Auth::check()) {
            $this->notifications = $this->notificationService->getNotifications();
            $this->unreadCount = $this->notificationService->getUnreadCount();
        }
    }

    public function toggleDropdown()
    {
        $this->showDropdown = !$this->showDropdown;
    }

    public function redirectToNotification($notificationId, $url)
    {
        // Marcar como leída en el localStorage del frontend
        $this->dispatch('mark-notification-read', notificationId: $notificationId);
        
        // Cerrar dropdown
        $this->showDropdown = false;
        
        // Redireccionar usando JavaScript
        $this->dispatch('redirect-to', url: $url);
    }

    public function markAllAsRead()
    {
        $this->dispatch('mark-all-notifications-read');
        $this->unreadCount = 0;
        $this->showDropdown = false;
    }

    // Método para refrescar las notificaciones cada 30 segundos
    public function refreshNotifications()
    {
        $this->loadNotifications();
    }

    public function render()
    {
        return view('livewire.notification-component');
    }
}
