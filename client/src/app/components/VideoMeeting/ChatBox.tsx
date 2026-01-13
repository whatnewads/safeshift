/**
 * Chat Box Component for SafeShift EHR
 * 
 * Real-time text chat for video meetings. Displays messages from all
 * participants with auto-scroll to latest messages.
 * 
 * @package SafeShift\Components\VideoMeeting
 */

import { useState, useRef, useEffect, useCallback } from 'react';
import { Send, MessageSquare } from 'lucide-react';
import { Button } from '../ui/button.js';
import { Input } from '../ui/input.js';
import { ScrollArea } from '../ui/scroll-area.js';
import type { ChatBoxProps, ChatMessage } from '../../types/video-meeting.types.js';

// ============================================================================
// Message Component
// ============================================================================

interface MessageItemProps {
  message: ChatMessage;
  isOwnMessage: boolean;
}

/**
 * Format timestamp for display
 */
function formatTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

/**
 * Single chat message component
 */
function MessageItem({ message, isOwnMessage }: MessageItemProps) {
  return (
    <div
      className={`flex flex-col gap-1 ${
        isOwnMessage ? 'items-end' : 'items-start'
      }`}
    >
      {/* Sender name (only for others' messages) */}
      {!isOwnMessage && (
        <span className="text-xs text-slate-500 dark:text-slate-400 px-1">
          {message.displayName}
        </span>
      )}
      
      {/* Message bubble */}
      <div
        className={`max-w-[80%] px-3 py-2 rounded-lg ${
          isOwnMessage
            ? 'bg-blue-600 text-white rounded-br-sm'
            : 'bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white rounded-bl-sm'
        }`}
      >
        <p className="text-sm break-words">{message.messageText}</p>
      </div>
      
      {/* Timestamp */}
      <span className="text-xs text-slate-400 dark:text-slate-500 px-1">
        {formatTime(message.sentAt)}
      </span>
    </div>
  );
}

// ============================================================================
// Chat Box Component
// ============================================================================

/**
 * Chat Box Component
 * 
 * Displays chat messages and input field for sending messages.
 * Auto-scrolls to latest message.
 * 
 * @example
 * ```tsx
 * <ChatBox
 *   messages={chatMessages}
 *   currentParticipantId={participant.participantId}
 *   onSendMessage={sendMessage}
 * />
 * ```
 */
export function ChatBox({
  messages,
  currentParticipantId,
  onSendMessage,
  loading = false,
}: ChatBoxProps) {
  const [inputValue, setInputValue] = useState('');
  const scrollAreaRef = useRef<HTMLDivElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Auto-scroll to bottom when new messages arrive
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const trimmedMessage = inputValue.trim();
      if (trimmedMessage && !loading) {
        onSendMessage(trimmedMessage);
        setInputValue('');
      }
    },
    [inputValue, loading, onSendMessage]
  );

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSubmit(e);
      }
    },
    [handleSubmit]
  );

  return (
    <div className="flex flex-col h-full bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
      {/* Header */}
      <div className="flex items-center gap-2 px-4 py-3 border-b border-slate-200 dark:border-slate-700">
        <MessageSquare className="h-5 w-5 text-slate-500 dark:text-slate-400" />
        <span className="font-medium text-slate-700 dark:text-slate-200">Chat</span>
        {messages.length > 0 && (
          <span className="text-xs text-slate-400 dark:text-slate-500">
            ({messages.length} messages)
          </span>
        )}
      </div>

      {/* Messages area */}
      <ScrollArea className="flex-1 p-4" ref={scrollAreaRef}>
        {messages.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-slate-400 dark:text-slate-500">
            <MessageSquare className="h-8 w-8 mb-2" />
            <p className="text-sm">No messages yet</p>
            <p className="text-xs">Start the conversation!</p>
          </div>
        ) : (
          <div className="flex flex-col gap-3">
            {messages.map((message) => (
              <MessageItem
                key={message.messageId}
                message={message}
                isOwnMessage={message.participantId === currentParticipantId}
              />
            ))}
            <div ref={messagesEndRef} />
          </div>
        )}
      </ScrollArea>

      {/* Input area */}
      <form
        onSubmit={handleSubmit}
        className="p-3 border-t border-slate-200 dark:border-slate-700"
      >
        <div className="flex gap-2">
          <Input
            type="text"
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Type a message..."
            disabled={loading}
            className="flex-1"
            maxLength={500}
          />
          <Button
            type="submit"
            size="sm"
            disabled={!inputValue.trim() || loading}
            className="px-3"
          >
            <Send className="h-4 w-4" />
          </Button>
        </div>
      </form>
    </div>
  );
}

export default ChatBox;
