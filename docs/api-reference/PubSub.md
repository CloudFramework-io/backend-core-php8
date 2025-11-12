# PubSub Class

## Overview

The `PubSub` class provides integration with Google Cloud Pub/Sub for asynchronous messaging between services. It allows publishing and subscribing to messages, enabling event-driven architectures and microservice communication.

## Loading the Class

```php
$pubsub = $this->core->loadClass('PubSub');
```

## Methods

### getTopics()

```php
public function getTopics(): array
```

Lists all Pub/Sub topics in the project.

**Returns:** Array of topic names

**Example:**
```php
$pubsub = $this->core->loadClass('PubSub');
$topics = $pubsub->getTopics();
// Returns: ['projects/my-project/topics/user-events', ...]
```

---

### getSubscriptions()

```php
public function getSubscriptions(): array
```

Lists all Pub/Sub subscriptions in the project.

**Returns:** Array of subscription names

---

### getSubscription()

```php
public function getSubscription($subscription, $topic = null): array
```

Gets information about a specific subscription.

**Parameters:**
- `$subscription` (string): Subscription name
- `$topic` (string|null): Optional topic name

**Returns:** Subscription details array

---

### subscribeTo()

```php
public function subscribeTo($subscriptionName, $topicName): bool
```

Creates a subscription to a topic.

**Parameters:**
- `$subscriptionName` (string): Name for the subscription
- `$topicName` (string): Topic to subscribe to

**Returns:** `true` on success

**Example:**
```php
$pubsub = $this->core->loadClass('PubSub');
$success = $pubsub->subscribeTo('my-subscription', 'user-events');
if($success) {
    // Subscription created
}
```

---

### unsubscribeTo()

```php
public function unsubscribeTo($subscriptionName, $topicName): bool
```

Deletes a subscription.

**Parameters:**
- `$subscriptionName` (string): Subscription to delete
- `$topicName` (string): Topic name

**Returns:** `true` on success

---

### pushMessage()

```php
public function pushMessage($message, $attributes = [], $topicName = null): bool
```

Publishes a message to a topic.

**Parameters:**
- `$message` (string): Message content
- `$attributes` (array): Optional message attributes (metadata)
- `$topicName` (string|null): Topic name

**Returns:** `true` on success

**Example:**
```php
$pubsub = $this->core->loadClass('PubSub');

// Simple message
$pubsub->pushMessage('User registered', [], 'user-events');

// Message with attributes
$pubsub->pushMessage(
    json_encode(['user_id' => 123, 'email' => 'user@example.com']),
    ['event' => 'registration', 'source' => 'api'],
    'user-events'
);
```

---

### pullMessages()

```php
public function pullMessages($subscriptionName, $topicName = null, $acknowledge = false): array
```

Pulls messages from a subscription.

**Parameters:**
- `$subscriptionName` (string): Subscription to pull from
- `$topicName` (string|null): Optional topic name
- `$acknowledge` (bool): Auto-acknowledge messages (default: false)

**Returns:** Array of messages

**Example:**
```php
$pubsub = $this->core->loadClass('PubSub');

// Pull messages
$messages = $pubsub->pullMessages('my-subscription');

foreach($messages as $message) {
    $data = $message['message']['data'];
    $attributes = $message['message']['attributes'];

    // Process message
    processEvent($data);
}

// Acknowledge if not auto-acknowledged
if(!$acknowledge) {
    $pubsub->acknowledgeLastMessages();
}
```

---

### acknowledgeLastMessages()

```php
public function acknowledgeLastMessages($id = null): bool
```

Acknowledges received messages to prevent redelivery.

**Parameters:**
- `$id` (string|null): Optional specific message ID

**Returns:** `true` on success

---

## Common Usage Patterns

### Publishing Events

```php
class API extends RESTful
{
    public function ENDPOINT_register()
    {
        // Create user
        $userId = $this->createUser($this->formParams);

        // Publish event
        $pubsub = $this->core->loadClass('PubSub');
        $pubsub->pushMessage(
            json_encode([
                'user_id' => $userId,
                'email' => $this->formParams['email'],
                'timestamp' => time()
            ]),
            ['event_type' => 'user_registered'],
            'user-events'
        );

        $this->addReturnData(['user_id' => $userId]);
    }
}
```

### Processing Messages (Script)

```php
class Script extends Scripts2020
{
    function main()
    {
        $pubsub = $this->core->loadClass('PubSub');

        while(true) {
            // Pull messages
            $messages = $pubsub->pullMessages('user-events-subscription');

            foreach($messages as $msg) {
                $data = json_decode($msg['message']['data'], true);

                // Process event
                $this->sendWelcomeEmail($data['user_id'], $data['email']);

                // Acknowledge
                $pubsub->acknowledgeLastMessages($msg['ackId']);
            }

            // Wait before next pull
            sleep(5);
        }
    }
}
```

### Event-Driven Architecture

```php
// Order Service: Publish order created event
$pubsub->pushMessage(
    json_encode(['order_id' => $orderId, 'user_id' => $userId, 'total' => $total]),
    ['event' => 'order_created'],
    'orders'
);

// Inventory Service: Subscribe to order events
$messages = $pubsub->pullMessages('inventory-subscription');
foreach($messages as $msg) {
    $order = json_decode($msg['message']['data'], true);
    $this->updateInventory($order);
}

// Email Service: Subscribe to order events
$messages = $pubsub->pullMessages('email-subscription');
foreach($messages as $msg) {
    $order = json_decode($msg['message']['data'], true);
    $this->sendOrderConfirmation($order);
}
```

---

## See Also

- [GCP Integration Guide](../guides/gcp-integration.md)
- [Core7 Class Reference](Core7.md)
