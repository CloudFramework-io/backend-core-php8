# WorkFlows Class

## Overview

The `WorkFlows` class provides workflow management functionality for orchestrating multi-step business processes. It helps manage state transitions, approvals, and automated task execution.

## Loading the Class

```php
$workflows = $this->core->loadClass('WorkFlows');
```

## Properties

| Property | Type | Description |
|----------|------|-------------|
| `$core` | Core7 | Reference to Core7 instance |
| `$error` | bool | Error flag |
| `$errorMsg` | array | Error messages |

---

## Common Usage Patterns

### Order Processing Workflow

```php
class API extends RESTful
{
    public function ENDPOINT_process_order()
    {
        $orderId = $this->params[0] ?? null;

        $workflows = $this->core->loadClass('WorkFlows');

        // Define workflow states
        $workflow = [
            'states' => [
                'pending' => ['next' => ['processing', 'cancelled']],
                'processing' => ['next' => ['shipped', 'cancelled']],
                'shipped' => ['next' => ['delivered', 'returned']],
                'delivered' => ['next' => ['completed']],
                'cancelled' => ['final' => true],
                'completed' => ['final' => true],
                'returned' => ['final' => true]
            ]
        ];

        // Get current order state
        $order = $this->getOrder($orderId);
        $currentState = $order['status'];

        // Transition to next state
        $newState = 'processing';

        if($workflows->canTransition($currentState, $newState, $workflow)) {
            $this->updateOrderStatus($orderId, $newState);

            // Trigger workflow actions
            $this->executeWorkflowActions($newState, $order);

            $this->addReturnData(['status' => $newState]);
        } else {
            return $this->setErrorFromCodelib('workflow-error', 'Invalid state transition');
        }
    }

    private function executeWorkflowActions($state, $order)
    {
        switch($state) {
            case 'processing':
                // Charge payment
                $this->chargePayment($order);
                // Send confirmation email
                $this->sendOrderConfirmation($order);
                break;

            case 'shipped':
                // Send tracking email
                $this->sendTrackingInfo($order);
                break;

            case 'delivered':
                // Request review
                $this->requestProductReview($order);
                break;
        }
    }
}
```

### Approval Workflow

```php
// Document approval workflow
$workflow = [
    'states' => [
        'draft' => ['next' => ['pending_review']],
        'pending_review' => ['next' => ['approved', 'rejected', 'needs_changes']],
        'needs_changes' => ['next' => ['pending_review']],
        'approved' => ['next' => ['published']],
        'published' => ['final' => true],
        'rejected' => ['final' => true]
    ],
    'permissions' => [
        'draft' => ['author'],
        'pending_review' => ['author'],
        'approved' => ['manager', 'admin'],
        'rejected' => ['manager', 'admin'],
        'published' => ['admin']
    ]
];

// Check if user can approve
public function canApprove($documentId, $userId)
{
    $document = $this->getDocument($documentId);
    $user = $this->getUser($userId);

    $workflows = $this->core->loadClass('WorkFlows');

    $currentState = $document['status'];
    $newState = 'approved';

    // Check transition is valid
    if(!$workflows->canTransition($currentState, $newState, $workflow)) {
        return false;
    }

    // Check user has permission
    $allowedRoles = $workflow['permissions'][$newState] ?? [];
    return in_array($user['role'], $allowedRoles);
}
```

### Task Workflow

```php
// Support ticket workflow
class API extends RESTful
{
    public function ENDPOINT_ticket_workflow()
    {
        $ticketId = $this->params[0] ?? null;
        $action = $this->params[1] ?? null;

        $workflows = $this->core->loadClass('WorkFlows');

        $workflow = [
            'states' => [
                'new' => [
                    'next' => ['assigned', 'closed'],
                    'actions' => ['assign', 'close']
                ],
                'assigned' => [
                    'next' => ['in_progress', 'closed'],
                    'actions' => ['start', 'close']
                ],
                'in_progress' => [
                    'next' => ['waiting_customer', 'resolved', 'closed'],
                    'actions' => ['wait_customer', 'resolve', 'close']
                ],
                'waiting_customer' => [
                    'next' => ['in_progress', 'closed'],
                    'actions' => ['resume', 'close']
                ],
                'resolved' => [
                    'next' => ['closed', 'in_progress'],
                    'actions' => ['close', 'reopen']
                ],
                'closed' => ['final' => true]
            ]
        ];

        $ticket = $this->getTicket($ticketId);

        // Execute action
        switch($action) {
            case 'assign':
                $this->assignTicket($ticketId, $this->formParams['agent_id']);
                $this->updateTicketStatus($ticketId, 'assigned');
                break;

            case 'start':
                $this->updateTicketStatus($ticketId, 'in_progress');
                break;

            case 'resolve':
                $this->updateTicketStatus($ticketId, 'resolved');
                $this->sendResolutionEmail($ticket);
                break;

            case 'close':
                $this->updateTicketStatus($ticketId, 'closed');
                break;
        }

        $this->addReturnData(['status' => 'success']);
    }
}
```

---

## Workflow Patterns

### State Machine

```php
$workflow = [
    'initial_state' => 'draft',
    'states' => [
        'draft' => ['next' => ['review']],
        'review' => ['next' => ['approved', 'rejected']],
        'approved' => ['final' => true],
        'rejected' => ['final' => true]
    ]
];
```

### Multi-Step Process

```php
$workflow = [
    'steps' => [
        ['name' => 'collect_info', 'required_fields' => ['name', 'email']],
        ['name' => 'verify_identity', 'required_fields' => ['id_number']],
        ['name' => 'payment', 'required_fields' => ['card_number']],
        ['name' => 'confirmation', 'final' => true]
    ]
];
```

---

## See Also

- [Core7 Class Reference](Core7.md)
- [DataStore Class Reference](DataStore.md)
- [API Development Guide](../guides/api-development.md)
