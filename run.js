#!/usr/bin/env node

require('dotenv').config();
var _ = require('underscore');
var moment = require('moment');
var db = require('mysql-promise')();

db.configure({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_DB
});

var irc = require('irc');
var client = new irc.Client(process.env.IRC_SERVER, process.env.IRC_NICK, {
    channels: [],
    userName: process.env.IRC_NICK,
    realName: process.env.IRC_NAME,
    password: process.env.IRC_PASSWORD,
    debug: true
});

(function checkChannelList() {
    db.query("SELECT * FROM `channels`").spread(function (rows) {
        var dbChans = [];
        _.each(rows, function (r) {
            dbChans.push('#' + r.channel);
        });

        // Join 'extra' channels
        _.each(_.difference(dbChans, client.opt.channels), function (chan) {
            client.join(chan);
        });

        // Part any channels we should no longer be in
        _.each(_.difference(client.opt.channels, dbChans), function (chan) {
            client.part(chan, 'Logging disabled for ' + chan);
        })
    });
    setTimeout(checkChannelList, 5000);
})();

function log(data) {
    /**
     * @todo Handle MySQL error, and save to a local file instead?
     */

    db.query("CREATE TABLE IF NOT EXISTS `" + data.channel + "` LIKE `template_chan`");
    db.query("CREATE TABLE IF NOT EXISTS `" + data.channel + "_dates` LIKE `template_dates`");

    var startOfDay = moment(data.time).startOf('day');

    db.query("INSERT INTO `" + data.channel + "_dates` (`date`) VALUES (?) ON DUPLICATE KEY UPDATE `date` = `date`", [startOfDay.unix()]);

    db.query("INSERT INTO `" + data.channel + "` (`nick`, `ident`, `host`, `type`, `message`, `time`, `target`) VALUES(?,?,?,?,?,?,?)", [
        data.nick,
        data.ident || '',
        data.host || '',
        data.type,
        data.message,
        +data.time / 1000,
        data.target || ''
    ]);
}

client.on('error', function (msg) {
    console.error(msg);
});

client.on('motd', function () {
    if (process.env.OPER_USER && process.env.OPER_PASSWORD) {
        client.send('OPER', process.env.OPER_USER, process.env.OPER_PASSWORD);
    }
});

client.on('message#', function (from, to, message, raw) {
    log({
        channel: to,
        nick: from,
        ident: raw.user,
        host: raw.host,
        type: 'msg',
        message: message,
        time: new Date()
    });
});

client.on('action', function (from, to, message, raw) {
    if (!_.contains(client.opt.channels, to)) {
        // Ignore, as it's a private notice
        return;
    }

    log({
        channel: to,
        nick: from,
        ident: raw.user,
        host: raw.host,
        type: 'action',
        message: message,
        time: new Date()
    });
});

client.on('notice', function (from, to, message, raw) {
    if (!_.contains(client.opt.channels, to)) {
        // Ignore, as it's a private notice
        return;
    }

    log({
        channel: to,
        nick: from,
        ident: raw.user,
        host: raw.host,
        type: 'notice',
        message: message,
        time: new Date()
    });
});

client.on('join', function (channel, nick, raw) {
    log({
        channel: channel,
        nick: nick,
        ident: raw.user,
        host: raw.host,
        type: 'join',
        message: channel,
        time: new Date()
    });
});

client.on('part', function (channel, nick, reason, raw) {
    log({
        channel: channel,
        nick: nick,
        ident: raw.user,
        host: raw.host,
        type: 'part',
        message: reason,
        time: new Date()
    });
});

client.on('kick', function (channel, nick, by, reason, raw) {
    log({
        channel: channel,
        nick: by,
        ident: raw.user,
        host: raw.host,
        type: 'kick',
        message: reason,
        time: new Date(),
        target: nick
    });
});

client.on('+mode', function (channel, by, mode, argument, raw) {
    log({
        channel: channel,
        nick: by,
        ident: raw.user,
        host: raw.host,
        type: 'mode',
        message: '+' + mode + ((argument) ? ' ' + argument : ''),
        time: new Date()
    });
});

client.on('-mode', function (channel, by, mode, argument, raw) {
    log({
        channel: channel,
        nick: by,
        ident: raw.user,
        host: raw.host,
        type: 'mode',
        message: '-' + mode + ((argument) ? ' ' + argument : ''),
        time: new Date()
    });
});

client.on('topic', function (channel, topic, nick, raw) {
    log({
        channel: channel,
        nick: nick,
        ident: raw.user,
        host: raw.host,
        type: 'topic',
        message: topic,
        time: new Date()
    });
});

client.on('quit', function (nick, reason, channels, raw) {
    _.each(channels, function (chan) {
        log({
            channel: chan,
            nick: nick,
            ident: raw.user,
            host: raw.host,
            type: 'quit',
            message: reason,
            time: new Date()
        });
    });
});

client.on('nick', function (oldNick, newNick, channels, raw) {
    _.each(channels, function (chan) {
        log({
            channel: chan,
            nick: oldNick,
            ident: raw.user,
            host: raw.host,
            type: 'nick',
            message: newNick,
            time: new Date()
        });
    });
});

client.on('ctcp-version', function (from, to, raw) {
    if (!_.contains(client.opt.channels, to)) {
        // Ignore, as it's a private notice
        return;
    }

    log({
        channel: to,
        nick: from,
        ident: raw.user,
        host: raw.host,
        type: 'ctcp',
        message: 'VERSION',
        time: new Date()
    });
});
