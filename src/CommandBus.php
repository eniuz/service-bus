<?php
/*
 * This file is part of the prooph/service-bus.
 * (c) 2014 - 2015 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Date: 09/14/14 - 16:32
 */

namespace Prooph\ServiceBus;

use Prooph\ServiceBus\Exception\RuntimeException;

/**
 * Class CommandBus
 *
 * A command bus is capable of dispatching a message to a command handler.
 * Only one handler per message is allowed!
 *
 * @package Prooph\ServiceBus
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
class CommandBus extends MessageBus
{
    /**
     * @param mixed $command
     * @return void
     * @throws Exception\MessageDispatchException
     */
    public function dispatch($command)
    {
        $actionEvent = $this->getActionEventEmitter()->getNewActionEvent();

        $actionEvent->setTarget($this);

        try {
            $this->initialize($command, $actionEvent);

            if ($actionEvent->getParam(self::EVENT_PARAM_MESSAGE_HANDLER) === null) {
                $actionEvent->setName(self::EVENT_ROUTE);

                $this->trigger($actionEvent);
            }

            if ($actionEvent->getParam(self::EVENT_PARAM_MESSAGE_HANDLER) === null) {
                throw new RuntimeException(sprintf(
                    "CommandBus was not able to identify a CommandHandler for command %s",
                    $this->getMessageType($command)
                ));
            }

            if (is_string($actionEvent->getParam(self::EVENT_PARAM_MESSAGE_HANDLER))) {
                $actionEvent->setName(self::EVENT_LOCATE_HANDLER);

                $this->trigger($actionEvent);
            }

            $commandHandler = $actionEvent->getParam(self::EVENT_PARAM_MESSAGE_HANDLER);

            if (is_callable($commandHandler)) {
                $commandHandler($command);
            } else {
                $actionEvent->setName(self::EVENT_INVOKE_HANDLER);
                $this->trigger($actionEvent);
            }

            $this->triggerFinalize($actionEvent);
        } catch (\Exception $ex) {
            $this->handleException($actionEvent, $ex);
        }
    }
}
